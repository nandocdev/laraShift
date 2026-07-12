<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

final readonly class RevokeOldestSessionAction
{
    /**
     * Revokes the oldest sessions if the limit is exceeded.
     */
    public function execute(CentralUser $user, int $limit = 3): void
    {
        $activeSessions = $user->centralSessions()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderBy('issued_at', 'asc')
            ->get();

        if ($activeSessions->count() > $limit) {
            $sessionsToRevoke = $activeSessions->take($activeSessions->count() - $limit);

            foreach ($sessionsToRevoke as $session) {
                $session->revoke('Concurrent limit exceeded');

                // If using database session driver, clean up Laravel session table
                if (config('session.driver') === 'database') {
                    DB::table('sessions')->where('id', $session->session_id)->delete();
                }

                activity('auth')
                    ->performedOn($user)
                    ->withProperties(['session_id' => $session->id])
                    ->log('central_session_revoked_by_limit');
            }
        }
    }
}
