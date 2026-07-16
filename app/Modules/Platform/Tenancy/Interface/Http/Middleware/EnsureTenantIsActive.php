<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Interface\Http\Middleware;

use App\Modules\Platform\Contracts\FeatureResolver;
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

        // 1. Whitelist critical routes
        if ($request->routeIs([
            'tenant.home',
            'login',
            'login.store',
            'logout',
            'two-factor.login',
            'two-factor.login.store',
            'tenant.invitations.accept',
            'tenant.support.auth',
            'payments.checkout.initiate',
            'tenant.billing.plans',
            'tenant.billing.manage',
            'tenant.billing.checkout.hosted',
            'tenant.billing.success',
            'tenant.billing.cancel',
            'tenant.billing.update-payment',
        ]) || $request->is('livewire/*', 'dashboard', 'auth/*', 'billing/*')) {
            return $next($request);
        }

        // Prime Features Cache (Redis-first)
        try {
            if (app()->bound(FeatureResolver::class)) {
                app(FeatureResolver::class)->execute(tenant());
            }
        } catch (\Exception $e) {
            // Log and continue if features can't be resolved
            \Log::warning('Could not resolve features for tenant: '.tenant('id'));
        }

        // 2. Allow pending_payment tenants through billing routes
        if (tenant('status') === 'pending_payment') {
            if ($request->routeIs([
                'tenant.billing.plans',
                'tenant.billing.manage',
                'tenant.billing.checkout.hosted',
                'tenant.billing.success',
                'tenant.billing.cancel',
                'tenant.billing.update-payment',
                'login',
                'login.store',
                'logout',
            ]) || $request->is('livewire/*', 'auth/*', 'billing/*')) {
                return $next($request);
            }

            return redirect()->route('tenant.billing.plans');
        }

        // 3. Hard block for archived tenants
        if (tenant('status') === 'archived') {
            abort(404);
        }

        // 3. Block for maintenance
        if (tenant('maintenance_mode')) {
            abort(503);
        }

        // 4. Enforce subscription/payment rules for AUTHENTICATED users
        if (auth()->check()) {
            $isSuspended = tenant('status') === 'suspended';
            $isPaidPlan = tenant('plan_id') !== 'free';

            if ($isSuspended || $isPaidPlan) {
                $subscription = tenant()->subscription('default');
                $hasActiveSubscription = $subscription && ($subscription->active() || $subscription->onGracePeriod());

                if ($isSuspended || ! $hasActiveSubscription) {
                    // Redirect to plans page instead of blocking
                    return redirect()->route('tenant.billing.plans');
                }
            }
        }

        if (tenant('read_only') && ! $request->isMethod('GET')) {
            abort(403, 'Tenant is in read-only mode.');
        }

        return $next($request);
    }
}
