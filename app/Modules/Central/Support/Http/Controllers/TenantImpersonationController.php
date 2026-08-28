<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Http\Controllers;

use App\Modules\Central\Support\Models\SupportSession;
use App\Modules\Central\Support\Notifications\ImpersonationEndedNotification;
use App\Modules\Platform\Foundation\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class TenantImpersonationController extends Controller
{
    /**
     * Authenticates an operator using a one-time token.
     * This route runs on the TENANT domain.
     */
    public function authenticate(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            abort(404);
        }

        $hashedToken = hash('sha256', $token);

        $session = SupportSession::where('token', $hashedToken)
            ->where('tenant_id', tenant('id'))
            ->where('expires_at', '>', now())
            ->whereNull('ended_at')
            ->firstOrFail();

        // Mitigate session fixation
        $request->session()->migrate(true);

        // 1. Authenticate the operator in the tenant guard
        // Note: central_users.id in tenant guard creates a shadow session; audit via Session::put impersonated_by
        // For true tenant user mapping, a dedicated SupportUser would be needed (future).
        auth()->loginUsingId($session->operator_id);

        // 2. Mark session as used/active
        Session::put('impersonation_session_id', $session->id);
        Session::put('impersonated_by', $session->operator_id);

        // 3. Clear token for security (one-time use for transition) — keep hashed to prevent reuse
        $session->update(['token' => hash('sha256', 'used_'.Str::random(10))]);

        return redirect()->intended('/dashboard')->with('status', __('Impersonation active. Actions are audited.'));
    }

    /**
     * Ends the impersonation session.
     */
    public function logout()
    {
        $sessionId = Session::get('impersonation_session_id');

        if ($sessionId) {
            $session = SupportSession::with('tenant')->find($sessionId);
            if ($session) {
                $session->update(['ended_at' => now()]);

                // Notify tenant as per PRD security requirement
                $session->tenant->notify(new ImpersonationEndedNotification(
                    $session->reason,
                    $session->started_at->format('Y-m-d H:i')
                ));
            }
        }

        auth()->logout();
        Session::forget(['impersonation_session_id', 'impersonated_by']);

        return redirect('/')->with('status', __('Impersonation session ended.'));
    }
}
