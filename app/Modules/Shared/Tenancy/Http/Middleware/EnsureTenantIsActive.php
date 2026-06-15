<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Http\Middleware;

use App\Modules\Central\Features\Actions\ResolveTenantFeaturesAction;
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
            'tenant.billing.update-payment'
        ]) || $request->is('livewire/*', 'dashboard', 'auth/*', 'billing/*')) {
            return $next($request);
        }

        // Prime Features Cache (Redis-first)
        try {
            app(ResolveTenantFeaturesAction::class)->execute(tenant());
        } catch (\Exception $e) {
            // Log and continue if features can't be resolved
            \Log::warning("Could not resolve features for tenant: " . tenant('id'));
        }

        // 2. Hard block for archived tenants
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
