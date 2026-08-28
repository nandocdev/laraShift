<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Support\ReservedSlugs;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates a tenant record synchronously (record + domain reservation + status
 * 'provisioning') and dispatches the async resumable provisioning pipeline.
 *
 * The heavy work (core data, infrastructure, admin user) runs in
 * ProvisionTenantJob. Charging for paid plans happens afterwards via the hosted
 * checkout flow; FulfillSubscription activates the tenant on payment approval.
 */
final readonly class CreateTenantAction
{
    public function __construct(
        private ReserveTenantDomainAction $reserveDomain,
    ) {}

    public function execute(CreateTenantData $data): Tenant
    {
        // Normalize slug to prevent Admin vs admin bypass (G005/P011)
        $normalizedSlug = strtolower(Str::slug($data->slug));
        if (ReservedSlugs::isReserved($normalizedSlug)) {
            throw new \RuntimeException(__('The slug ":slug" is reserved. Please choose another.', ['slug' => $normalizedSlug]));
        }

        $data = new CreateTenantData(
            name: strip_tags($data->name),
            slug: $normalizedSlug,
            email: $data->email,
            plan_id: $data->plan_id,
            password: $data->password,
            payment_token: $data->payment_token,
            status: $data->status,
        );

        $lock = Cache::lock("provisioning:slug:{$data->slug}", 10);
        if (! $lock->get()) {
            throw new \RuntimeException(__('The slug ":slug" is currently being provisioned. Please try again.', ['slug' => $data->slug]));
        }

        try {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::where('slug', $data->slug)->first();

            if ($tenant && $tenant->status !== 'failed') {
                throw new \RuntimeException("Tenant with slug {$data->slug} already exists and is not in a failed state.");
            }

            try {
                return DB::transaction(function () use ($data, &$tenant) {
                    // Re-check within lock/transaction
                    $fresh = Tenant::where('slug', $data->slug)->lockForUpdate()->first();
                    if ($fresh) {
                        $tenant = $fresh;
                    }
                    if ($tenant) {
                        // Reset failed tenant for retry
                        $tenant->update([
                            'name' => $data->name,
                            'email' => $data->email,
                            'plan_id' => $data->plan_id,
                            'status' => 'provisioning',
                        ]);

                        // Clean up partial logs to avoid confusion
                        $tenant->provisioningLogs()->delete();
                    } else {
                        $tenant = Tenant::create([
                            'id' => Str::uuid()->toString(),
                            'slug' => $data->slug,
                            'name' => $data->name,
                            'email' => $data->email,
                            'plan_id' => $data->plan_id,
                            'status' => 'provisioning',
                        ]);
                    }

                    // Step 1: Subdomain / Domain Reservation (synchronous, required
                    // for the hosted checkout URLs and tenant routes)
                    $this->reserveDomain->execute($tenant, $data->slug);

                    // Dispatch the async pipeline after commit — a queue failure
                    // must not roll back the tenant creation.
                    DB::afterCommit(fn () => ProvisionTenantJob::dispatch(
                        tenantId: $tenant->id,
                        adminEmail: $data->email,
                        password: $data->password,
                        adminName: 'Administrator',
                        finalStatus: $data->status,
                    ));

                    activity('provisioning')
                        ->performedOn($tenant)
                        ->log('tenant_provisioning_queued');

                    return $tenant;
                });
            } catch (UniqueConstraintViolationException $e) {
                throw new \RuntimeException(
                    __('The slug ":slug" was just taken by another registration. Please try a different one.', ['slug' => $data->slug]),
                    previous: $e,
                );
            } catch (\Throwable $e) {
                Log::error("Tenant creation failed for slug {$data->slug}: ".$e->getMessage());

                throw $e;
            }
        } finally {
            $lock->release();
        }
    }
}
