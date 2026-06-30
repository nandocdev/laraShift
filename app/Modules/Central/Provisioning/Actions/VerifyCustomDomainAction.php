<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Domain;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Log;

final readonly class VerifyCustomDomainAction
{
    private const string DNS_TEMPLATE = '_larashift.%s';

    private const string EXPECTED_VALUE = 'verified.larashift.app';

    /**
     * Verify a custom domain via DNS TXT record check.
     *
     * @return array{verified: bool, records: array<int, string>, error?: string}
     */
    public function verify(Tenant $tenant, string $domain): array
    {
        $txtRecord = sprintf(self::DNS_TEMPLATE, $domain);

        try {
            $records = dns_get_record($txtRecord, DNS_TXT);

            if ($records === false || empty($records)) {
                return [
                    'verified' => false,
                    'records' => [],
                    'error' => "No DNS TXT records found for {$txtRecord}.",
                ];
            }

            $values = array_map(fn ($r) => trim($r['txt'] ?? '', '"'), $records);
            $matched = in_array(self::EXPECTED_VALUE, $values, true);

            if ($matched) {
                Domain::updateOrCreate(
                    ['domain' => $domain, 'tenant_id' => $tenant->id],
                    ['verified_at' => now()],
                );

                Log::info("Custom domain verified: {$domain}", ['tenant_id' => $tenant->id]);

                activity('provisioning')
                    ->performedOn($tenant)
                    ->withProperties(['domain' => $domain])
                    ->log('custom_domain_verified');
            }

            return [
                'verified' => $matched,
                'records' => $values,
            ];
        } catch (\Throwable $e) {
            Log::warning("Custom domain verification failed: {$domain}", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'verified' => false,
                'records' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check the verification status without performing a new lookup.
     */
    public function checkStatus(Tenant $tenant, string $domain): bool
    {
        return Domain::where('tenant_id', $tenant->id)
            ->where('domain', $domain)
            ->whereNotNull('verified_at')
            ->exists();
    }
}
