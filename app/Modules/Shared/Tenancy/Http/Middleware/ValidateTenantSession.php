<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ValidateTenantSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('tenant') || ! tenant()) {
            return $next($request);
        }

        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        $userTenantId = $user->tenant_id ?? $user->getAttribute('tenant_id');

        if ($userTenantId) {
            $currentTenantId = (string) tenant('id');

            if ((string) $userTenantId !== $currentTenantId) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->guest(route('login'))
                    ->with('error', __('Your session is not valid for this tenant.'));
            }
        }

        return $next($request);
    }
}
