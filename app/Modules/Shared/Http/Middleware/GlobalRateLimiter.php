<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class GlobalRateLimiter
{
    private const int DEFAULT_MAX_ATTEMPTS = 120;

    private const int DEFAULT_DECAY_SECONDS = 60;

    public function __construct(
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int $decaySeconds = self::DEFAULT_DECAY_SECONDS,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ipKey = $this->ipKey($request);
        $tenantKey = $this->tenantKey($request);

        try {
            if (RateLimiter::tooManyAttempts($ipKey, $this->maxAttempts)) {
                return $this->rateLimitResponse($request, $ipKey);
            }

            if ($tenantKey && RateLimiter::tooManyAttempts($tenantKey, $this->tenantLimit())) {
                return $this->rateLimitResponse($request, $tenantKey);
            }

            RateLimiter::hit($ipKey, $this->decaySeconds);

            if ($tenantKey) {
                RateLimiter::hit($tenantKey, $this->decaySeconds);
            }
        } catch (\Throwable $e) {
            Log::warning('Global rate limiter failed', ['error' => $e->getMessage()]);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($ipKey, $this->maxAttempts));

        return $response;
    }

    private function ipKey(Request $request): string
    {
        return 'global_rate_ip:'.$request->ip();
    }

    private function tenantKey(Request $request): ?string
    {
        if (! function_exists('tenant') || ! tenant()) {
            return null;
        }

        return 'global_rate_tenant:'.tenant('id');
    }

    private function tenantLimit(): int
    {
        return (int) config('tenancy.rate_limit.global', 1000);
    }

    private function rateLimitResponse(Request $request, string $key): Response
    {
        $seconds = RateLimiter::availableIn($key);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
            ], 429, ['Retry-After' => $seconds]);
        }

        return response('Too Many Requests', 429, ['Retry-After' => $seconds]);
    }
}
