<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    /**
     * Handle an incoming request.
     * 
     * Ensures that the authenticated user belongs to the current tenant.
     * Mandatory for strict isolation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $tenantId = (string) tenant('id');

        if ($user && (string) $user->tenant_id !== $tenantId) {
            // Architecture rule: Cross-tenant access MUST result in 404, never 403.
            abort(404, __('The page you are looking for does not exist or you do not have permission to access it.'));
        }

        return $next($request);
    }
}
