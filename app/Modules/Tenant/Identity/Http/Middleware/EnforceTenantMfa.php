<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Http\Middleware;

use App\Modules\Tenant\Settings\Models\TenantSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (! $user) {
            return $next($request);
        }

        // 1. Check if we are already on the enrollment page to avoid loops
        if ($request->routeIs('tenant.settings.security.2fa')) {
            return $next($request);
        }

        // 2. Check tenant settings
        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();
        
        if ($settings && $settings->mfa_required && ! $user->mfa_enabled) {
            return redirect()->route('tenant.settings.security.2fa')
                ->with('error', __('MFA is mandatory for this organization. Please complete your setup.'));
        }

        return $next($request);
    }
}
