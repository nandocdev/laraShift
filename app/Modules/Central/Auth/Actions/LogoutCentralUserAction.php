<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\Models\CentralSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final readonly class LogoutCentralUserAction
{
    /**
     * Procesa el cierre de sesión para el área central.
     */
    public function execute(): void
    {
        $sessionId = Session::getId();

        Auth::guard('central')->logout();

        // Mark central_session as revoked
        CentralSession::where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        Session::invalidate();
        Session::regenerateToken();
    }
}
