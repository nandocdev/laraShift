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
        $current = Session::getId();

        $others = TenantSession::where('user_id', $user->getKey())
            ->where('session_id', '!=', $current)
            ->whereNull('revoked_at')
            ->get();

        if ($others->isEmpty()) {
            return 0;
        }

        TenantSession::where('user_id', $user->getKey())
            ->where('session_id', '!=', $current)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->whereIn('id', $others->pluck('session_id'))
                ->delete();
        }

        activity('auth')
            ->performedOn($user)
            ->withProperties(['revoked_count' => $others->count()])
            ->log('tenant_sessions_revoked');

        return $others->count();
    }
}
