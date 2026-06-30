<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyMiddleware
{
    private const int DEFAULT_TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') && ! $request->isMethod('PATCH') && ! $request->isMethod('PUT')) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        if (! preg_match('/^[a-zA-Z0-9\-_]{8,64}$/', $key)) {
            return response()->json([
                'message' => 'Invalid Idempotency-Key format.',
                'code' => 'invalid_idempotency_key',
            ], 400);
        }

        $cacheKey = 'idempotency:'.$key;

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $response = unserialize($cached);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, serialize($response), now()->addHours(self::DEFAULT_TTL_HOURS));
        }

        return $response;
    }
}
