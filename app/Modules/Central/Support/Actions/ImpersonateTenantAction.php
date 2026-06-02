<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Support\Str;

final readonly class ImpersonateTenantAction
{
    /**
     * Initiates an audited impersonation session.
     * 
     * [SIDE-EFFECTS]
     * - Records the session with reason.
     * - Generates a secure one-time token.
     * - Returns the redirect URL to the tenant domain.
     */
    public function execute(Tenant $tenant, string $reason): string
    {
        if (strlen($reason) < 20) {
            throw new \InvalidArgumentException(__('Reason must be at least 20 characters long.'));
        }

        $session = SupportSession::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'operator_id' => auth('central')->id(),
            'reason' => $reason,
            'token' => Str::random(64),
            'started_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        activity('support')
            ->performedOn($tenant)
            ->withProperties(['session_id' => $session->id, 'reason' => $reason])
            ->log('impersonation_started');

        // Construct transition URL: http://tenant.domain.com/support/auth?token=XXX
        $domain = $tenant->domains->first()?->domain;
        
        if (! $domain) {
            throw new \RuntimeException(__('Tenant has no primary domain configured.'));
        }

        return "http://{$domain}/support/auth?token={$session->token}";
    }
}
