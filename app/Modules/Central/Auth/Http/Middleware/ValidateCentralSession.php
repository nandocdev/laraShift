<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Http\Middleware;

use App\Modules\Central\Auth\Models\CentralSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class ValidateCentralSession
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the current session is valid and not revoked in the
     * central_sessions tracking table.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('central')->check()) {
            $sessionId = Session::getId();

            $trackingSession = CentralSession::where('session_id', $sessionId)
                ->where('user_id', Auth::guard('central')->id())
                ->first();

            // If tracking record is missing or revoked, force logout
            if (! $trackingSession || $trackingSession->revoked_at || $trackingSession->expires_at->isPast()) {
                Auth::guard('central')->logout();
                Session::invalidate();
                Session::regenerateToken();

                return redirect()->route('central.login')
                    ->with('error', __('Your session has been terminated or expired for security reasons.'));
            }
        }

        return $next($request);
    }
}
