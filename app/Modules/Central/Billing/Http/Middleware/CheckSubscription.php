<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('tenant') && tenant()) {
            $tenant = tenant();

            $subscription = $tenant->subscription('default');

            if (! $subscription || ! $subscription->active()) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => __('No active subscription.')], 402);
                }

                return redirect()->route('tenant.billing.manage')
                    ->with('error', __('Your subscription is inactive. Please update your payment method.'));
            }
        }

        return $next($request);
    }
}
