<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Tenancy\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return $next($request);
        }

        $tenant = $this->resolver->resolve($request);

        if ($tenant && function_exists('tenancy')) {
            tenancy()->initialize($tenant);
        }

        return $next($request);
    }
}
