<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Http\Middleware;

use App\Modules\Central\Billing\Models\Plan;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
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
        $limitRpm = $this->resolveLimit($tenant);
        $key = 'tenant_rate_limit:' . $tenant->id;

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
            Log::warning('Rate limiter failed for tenant ' . $tenant->id . ': ' . $e->getMessage());
        }

        $response = $next($request);

        // Add headers to response
        if (! $response->isServerError()) {
            $response->headers->set('X-RateLimit-Limit', (string) $limitRpm);
            $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $limitRpm));
        }

        return $response;
    }

    protected function resolveLimit($tenant): int
    {
        // Default fallback
        $defaultLimit = 60;

        try {
            $plan = $tenant->plan; // Assuming relation exists or plan_id is used to find
            
            if (! $plan) {
                // Find by ID or Slug if relation not loaded
                $plan = \App\Modules\Central\Billing\Models\Plan::where('id', $tenant->plan_id)
                    ->orWhere('slug', $tenant->plan_id)
                    ->first();
            }

            return (int) ($plan->features['quotas']['rate_limit_rpm'] ?? $defaultLimit);
        } catch (\Exception $e) {
            return $defaultLimit;
        }
    }
}
