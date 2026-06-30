<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Logging;

use App\Modules\Shared\Http\Middleware\CorrelationId;
use App\Modules\Shared\Http\Middleware\TraceContext;
use Illuminate\Support\Facades\Log;

final readonly class TenantLogger
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function emergency(string $message, array $extra = []): void
    {
        Log::emergency($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function alert(string $message, array $extra = []): void
    {
        Log::alert($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function critical(string $message, array $extra = []): void
    {
        Log::critical($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function error(string $message, array $extra = []): void
    {
        Log::error($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function warning(string $message, array $extra = []): void
    {
        Log::warning($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function notice(string $message, array $extra = []): void
    {
        Log::notice($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function info(string $message, array $extra = []): void
    {
        Log::info($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function debug(string $message, array $extra = []): void
    {
        Log::debug($message, $this->enrich($extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function enrich(array $extra): array
    {
        $context = [];

        if (function_exists('tenant') && tenant()) {
            $context['tenant_id'] = tenant('id');
        }

        $correlationId = CorrelationId::current();
        if ($correlationId) {
            $context['correlation_id'] = $correlationId;
        }

        $traceId = TraceContext::currentTraceId();
        if ($traceId) {
            $context['trace_id'] = $traceId;
            $context['span_id'] = TraceContext::currentSpanId();
        }

        return array_merge($context, $extra);
    }
}
