<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasFeature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (function_exists('tenant') && tenant() && ! tenant()->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Feature :feature is not available in your current plan.', ['feature' => $feature]),
                    'code' => 'feature_not_available',
                ], 403);
            }

            return abort(403, __('Feature :feature is not available in your current plan.', ['feature' => $feature]));
        }

        return $next($request);
    }
}
