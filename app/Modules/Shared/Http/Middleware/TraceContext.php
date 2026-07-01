<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TraceContext
{
    public const string TRACEPARENT_HEADER = 'traceparent';

    public const string TRACESTATE_HEADER = 'tracestate';

    public function handle(Request $request, Closure $next): Response
    {
        $traceparent = $request->header(self::TRACEPARENT_HEADER);

        if ($traceparent && $this->isValidTraceparent($traceparent)) {
            $parts = explode('-', $traceparent);
            $traceId = $parts[1] ?? null;
            $spanId = $this->generateSpanId();
            $version = $parts[0] ?? '00';
            $traceFlags = $parts[3] ?? '01';
        } else {
            $traceId = $this->generateTraceId();
            $spanId = $this->generateSpanId();
            $version = '00';
            $traceFlags = '01';
        }

        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('span_id', $spanId);

        $response = $next($request);

        $response->headers->set(
            self::TRACEPARENT_HEADER,
            sprintf('%s-%s-%s-%s', $version, $traceId, $spanId, $traceFlags),
        );

        return $response;
    }

    public static function currentTraceId(?Request $request = null): ?string
    {
        $request ??= request();

        return $request?->attributes->get('trace_id');
    }

    public static function currentSpanId(?Request $request = null): ?string
    {
        $request ??= request();

        return $request?->attributes->get('span_id');
    }

    private function isValidTraceparent(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{2}-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/', $value);
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
