<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Interface\Http\Middleware;

use App\Modules\Platform\Contracts\TenantContract;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantRateLimits
{
    /**
     * Handle an incoming request.
     *
     * [RIESGOS]
     * - If Redis is unavailable, the system fails open to prevent complete outage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('tenant') || ! tenant()) {
            return $next($request);
        }

        $tenant = tenant();
        $limitRpm = 60;

        if ($tenant instanceof TenantContract) {
            $limit = $tenant->getQuotaLimit('rate_limit_rpm');
            if ($limit > 0) {
                $limitRpm = $limit;
            }
        }

        $key = 'tenant_rate_limit:'.$tenant->id;

        try {
            if (RateLimiter::tooManyAttempts($key, $limitRpm)) {
                $seconds = RateLimiter::availableIn($key);

                return response()->json([
                    'error' => 'Too Many Requests',
                    'message' => __('Rate limit exceeded for your plan. Please try again in :seconds seconds.', ['seconds' => $seconds]),
                ], 429, [
                    'Retry-After' => $seconds,
                    'X-RateLimit-Limit' => $limitRpm,
                    'X-RateLimit-Remaining' => 0,
                ]);
            }

            RateLimiter::hit($key, 60); // 1 minute window
        } catch (\Exception $e) {
            // Fail open: log warning but allow request if Redis is down
            Log::warning('Rate limiter failed for tenant '.$tenant->id.': '.$e->getMessage());
        }

        $response = $next($request);

        // Add headers to response
        if (! $response->isServerError()) {
            $response->headers->set('X-RateLimit-Limit', (string) $limitRpm);
            $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $limitRpm));
        }

        return $response;
    }
}
