<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     * 
     * Ensures that the authenticated user is active. 
     * If not, it logs them out and redirects to login.
     * This provides instantaneous session invalidation (< 60s).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', __('Your access has been revoked. Please contact your administrator.'));
        }

        return $next($request);
    }
}
