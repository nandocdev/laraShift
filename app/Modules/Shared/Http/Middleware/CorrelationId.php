<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\ValueObjects\Uuid;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public const string HEADER_NAME = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER_NAME);

        if (! $correlationId) {
            $correlationId = Uuid::generate()->value();
        }

        $request->attributes->set('correlation_id', $correlationId);

        $response = $next($request);

        $response->headers->set(self::HEADER_NAME, $correlationId);

        return $response;
    }

    public static function current(?Request $request = null): ?string
    {
        $request ??= request();

        return $request?->attributes->get('correlation_id');
    }
}
