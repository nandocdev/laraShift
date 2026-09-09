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

        // 1. Strict interception for suspended tenants
        if (tenant('status') === 'suspended') {
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
                'two-factor.login',
                'two-factor.login.store',
            ]) || $request->is('livewire/*', 'auth/*', 'billing/*')) {
                // Allow these routes to enable login and payment recovery
            } else {
                if (auth()->check()) {
                    return redirect()->route('tenant.billing.plans');
                }
                abort(402, 'This account has been suspended due to overdue payment.');
            }
        }

        // 2. Whitelist critical routes for active/pending_payment tenants
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

        // 5. Hard block for archived/expired/quarantined tenants
        if (in_array(tenant('status'), ['archived', 'expired', 'quarantine'], true)) {
            abort(tenant('status') === 'quarantine' ? 403 : 404);
        }

        // 6. Block for maintenance
        if (tenant('maintenance_mode')) {
            abort(503);
        }

        // 7. Enforce subscription/payment rules for AUTHENTICATED users
        if (auth()->check()) {
            $isPaidPlan = tenant('plan_id') !== 'free';

            if ($isPaidPlan) {
                $subscription = tenant()->subscription('default');
                $hasActiveSubscription = $subscription && (
                    $subscription->active() ||
                    $subscription->onGracePeriod() ||
                    tenant('status') === 'past_due'
                );

                if (! $hasActiveSubscription) {
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
