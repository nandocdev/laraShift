<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('tenant') || ! tenant()) {
            return $next($request);
        }

        // 1. Strict interception for suspended tenants
        if (tenant('status') === 'suspended') {
            if ($request->routeIs([
                'login',
                'login.store',
                'logout',
                'two-factor.login',
                'two-factor.login.store',
                'tenant.billing.*',
            ]) || $request->is('livewire/*', 'auth/*')) {
                // Login + billing (regularize payment in dunning).
            } else {
                abort(403, 'This account has been suspended.');
            }
        }

        // 2. Whitelist critical routes
        if ($request->routeIs([
            'tenant.home',
            'login',
            'login.store',
            'logout',
            'two-factor.login',
            'two-factor.login.store',
            'tenant.invitations.accept',
            'tenant.support.auth',
        ]) || $request->is('livewire/*', 'dashboard', 'auth/*')) {
            return $next($request);
        }

        // 2b. Billing dunning lane: pending_payment tenants may only
        // checkout, browse billing or log in; everything else is blocked here.
        if (tenant('status') === 'pending_payment') {
            if ($request->routeIs(['payments.checkout.initiate', 'payments.clave.callback', 'tenant.billing.*'])) {
                return $next($request);
            }

            abort(402, 'Payment required to activate this workspace.');
        }

        // 3. Hard block for archived/expired/quarantined tenants
        if (in_array(tenant('status'), ['archived', 'expired', 'quarantine'], true)) {
            abort(tenant('status') === 'quarantine' ? 403 : 404);
        }

        // 4. Block for maintenance
        if (tenant('maintenance_mode')) {
            abort(503);
        }

        if (tenant('read_only') && ! $request->isMethod('GET')) {
            abort(403, 'Tenant is in read-only mode.');
        }

        return $next($request);
    }
}
