<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Http\Middleware;

use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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
        $mfaRequired = Cache::remember("tenant:{tenant('id')}:mfa_required", now()->addHour(), function () {
            return (bool) TenantSetting::where('tenant_id', tenant('id'))->value('mfa_required');
        });

        if ($mfaRequired && ! $user->mfa_enabled) {
            return redirect()->route('tenant.settings.security.2fa')
                ->with('error', __('MFA is mandatory for this organization. Please complete your setup.'));
        }

        return $next($request);
    }
}
