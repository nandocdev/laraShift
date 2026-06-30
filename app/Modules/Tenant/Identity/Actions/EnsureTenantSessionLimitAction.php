<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\TenantSession;
use Illuminate\Support\Facades\DB;

final readonly class EnsureTenantSessionLimitAction
{
    /**
     * Revoke the oldest sessions if the limit is exceeded.
     */
    public function execute(string $tenantId, string $userId, int $limit = 3): void
    {
        $activeSessions = TenantSession::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->active()
            ->orderBy('issued_at', 'asc')
            ->get();

        if ($activeSessions->count() > $limit) {
            $sessionsToRevoke = $activeSessions->take($activeSessions->count() - $limit);

            foreach ($sessionsToRevoke as $session) {
                $session->revoke(null, 'Concurrent session limit exceeded');

                if (config('session.driver') === 'database') {
                    DB::table('sessions')
                        ->where('id', $session->session_id)
                        ->delete();
                }
            }
        }
    }

    /**
     * Resolve the session limit based on the tenant's plan.
     */
    public function resolveLimit($tenant): int
    {
        try {
            $plan = $tenant->plan;

            return (int) ($plan->features['quotas']['max_sessions'] ?? 3);
        } catch (\Throwable) {
            return 3;
        }
    }
}
