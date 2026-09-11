<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Application\Actions;

use App\Modules\Central\Growth\Domain\ValueObjects\FraudSignals;
use App\Modules\Central\Growth\Infrastructure\Notifications\SecOpsAlertNotification;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Applies quarantine to a tenant and dispatches the SecOps alert.
 *
 * Called from ProvisionTenantPipeline when finalStatus === 'quarantine'.
 * The tenant's status was already set to 'quarantine' by the pipeline;
 * this action exists to keep the notification side-effect isolated and testable.
 */
final readonly class QuarantineTenantAction
{
    public function execute(Tenant $tenant, FraudSignals $fraudSignals): void
    {
        $tenant->update([
            'status' => 'quarantine',
            'read_only' => true,
        ]);

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties($fraudSignals->toArray())
            ->log('tenant_quarantined');

        $secopsEmail = config('fraud.secops_email');

        if ($secopsEmail) {
            /** @var AnonymousNotifiable $notifiable */
            $notifiable = (new AnonymousNotifiable)->route('mail', $secopsEmail);
            $notifiable->notify(new SecOpsAlertNotification(
                tenantId: $tenant->id,
                tenantSlug: $tenant->slug,
                fraudSignals: $fraudSignals,
            ));
        }
    }
}
