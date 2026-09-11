<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

final readonly class RevokeOtherTenantSessions
{
    public function execute(User $user): int
    {
        return $this->revokeExcept($user, Session::getId());
    }

    /**
     * Revokes every session of the user, including the current one.
     * Used after a forgotten-password reset (possible compromise).
     */
    public function revokeAll(User $user): int
    {
        return $this->revokeExcept($user, null);
    }

    private function revokeExcept(User $user, ?string $keepSessionId): int
    {
        $query = TenantSession::where('user_id', $user->getKey())
            ->whereNull('revoked_at');

        if ($keepSessionId !== null) {
            $query->where('session_id', '!=', $keepSessionId);
        }

        $targets = $query->get();

        if ($targets->isEmpty()) {
            return 0;
        }

        $revoke = TenantSession::where('user_id', $user->getKey())
            ->whereNull('revoked_at');

        if ($keepSessionId !== null) {
            $revoke->where('session_id', '!=', $keepSessionId);
        }

        $revoke->update(['revoked_at' => now()]);

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->whereIn('id', $targets->pluck('session_id'))
                ->delete();
        }

        activity('auth')
            ->performedOn($user)
            ->withProperties(['revoked_count' => $targets->count()])
            ->log('tenant_sessions_revoked');

        return $targets->count();
    }
}
