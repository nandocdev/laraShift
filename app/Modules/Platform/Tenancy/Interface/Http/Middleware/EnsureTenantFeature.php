<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Interface\Http\Middleware;

use App\Modules\Platform\Contracts\TenantFeatureResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantFeature
{
    /**
     * Usage: ->middleware('feature:api_access').
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! app(TenantFeatureResolver::class)->hasFeature(tenant(), $feature)) {
            abort(403, __('This action is not available on your current plan.'));
        }

        return $next($request);
    }
}
