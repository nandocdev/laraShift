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

        // Prime Features Cache (Redis-first)
        app(ResolveTenantFeaturesAction::class)->execute(tenant());

        if (tenant('maintenance_mode')) {
            abort(503, 'Tenant is undergoing maintenance.');
        }

        if (tenant('status') === 'archived') {
            abort(404); // Architecture rule: preserved isolation/404 for non-active
        }

        if (tenant('status') === 'suspended') {
            abort(402, 'Payment Required');
        }

        // Check subscription for paid plans
        if (tenant('plan_id') !== 'free') {
            $subscription = tenant()->subscription('default');
            
            if (! $subscription || ! $subscription->active()) {
                // If it's not active but on grace period, allow it
                if (! $subscription?->onGracePeriod()) {
                    abort(402, 'Active subscription required.');
                }
            }
        }

        if (tenant('read_only') && ! $request->isMethod('GET')) {
            abort(403, 'Tenant is in read-only mode.');
        }

        return $next($request);
    }
}
