<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Http\Middleware;

use App\Modules\Shared\Infrastructure\Exceptions\QuotaExceededException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinQuota
{
    /**
     * Handle an incoming request.
     *
     * @throws QuotaExceededException
     */
    public function handle(Request $request, Closure $next, string $metric): Response
    {
        if (function_exists('tenant') && tenant() && ! tenant()->withinQuota($metric)) {
            throw new QuotaExceededException($metric);
        }

        return $next($request);
    }
}
