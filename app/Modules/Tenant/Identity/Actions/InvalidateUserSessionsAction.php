<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\TenantSession;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class InvalidateUserSessionsAction
{
    /**
     * Revoke all active sessions for a user.
     * Used by tenant admin to force logout a user.
     */
    public function execute(User $target, User $admin): int
    {
        $count = TenantSession::where('tenant_id', $target->tenant_id)
            ->where('user_id', $target->id)
            ->active()
            ->count();

        TenantSession::where('tenant_id', $target->tenant_id)
            ->where('user_id', $target->id)
            ->active()
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $admin->id,
                'revoke_reason' => 'Revoked by admin',
            ]);

        if (config('session.driver') === 'database') {
            $sessionIds = TenantSession::where('tenant_id', $target->tenant_id)
                ->where('user_id', $target->id)
                ->whereNotNull('revoked_at')
                ->pluck('session_id');

            DB::table('sessions')->whereIn('id', $sessionIds)->delete();
        }

        activity('auth')
            ->performedOn($target)
            ->causedBy($admin)
            ->withProperties(['revoked_sessions' => $count])
            ->log('tenant_user_sessions_revoked');

        return $count;
    }
}
