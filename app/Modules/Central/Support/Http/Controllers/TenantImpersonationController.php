<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Http\Controllers;

use App\Modules\Central\Support\Models\SupportSession;
use App\Modules\Shared\Http\Controllers\Controller;
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

        $session = SupportSession::where('token', $token)
            ->where('tenant_id', tenant('id'))
            ->where('expires_at', '>', now())
            ->whereNull('ended_at')
            ->firstOrFail();

        // 1. Mark as used/active
        Session::put('impersonation_session_id', $session->id);
        Session::put('impersonated_by', $session->operator_id);

        // 2. Clear token for security (one-time use for transition)
        $session->update(['token' => 'used_' . Str::random(10)]);

        return redirect('/dashboard')->with('status', __('Impersonation active. Actions are audited.'));
    }

    /**
     * Ends the impersonation session.
     */
    public function logout()
    {
        $sessionId = Session::get('impersonation_session_id');

        if ($sessionId) {
            $session = SupportSession::find($sessionId);
            $session?->update(['ended_at' => now()]);
        }

        Session::forget(['impersonation_session_id', 'impersonated_by']);

        return redirect('/')->with('status', __('Impersonation session ended.'));
    }
}
