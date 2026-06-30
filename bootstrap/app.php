<?php

use App\Modules\Shared\Exceptions\HttpExceptionMap;
use App\Modules\Shared\Http\Middleware\CorrelationId;
use App\Modules\Shared\Http\Middleware\GlobalRateLimiter;
use App\Modules\Shared\Http\Middleware\ResolveTenant;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use App\Modules\Shared\Http\Middleware\TraceContext;
use App\Modules\Shared\Infrastructure\Exceptions\QuotaExceededException;
use App\Modules\Shared\Tenancy\Http\Middleware\EnsureHasFeature;
use App\Modules\Shared\Tenancy\Http\Middleware\EnsureUserQuota;
use App\Modules\Shared\Tenancy\Http\Middleware\EnsureWithinQuota;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $request) => (function_exists('tenant') && tenant()) ? route('login') : route('central.login'));
        $middleware->appendToGroup('universal', [
            CorrelationId::class,
            TraceContext::class,
        ]);

        $middleware->appendToGroup('web', [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'feature' => EnsureHasFeature::class,
            'quota' => EnsureWithinQuota::class,
            'resolve-tenant' => ResolveTenant::class,
            'throttle.global' => GlobalRateLimiter::class,
            'user-quota' => EnsureUserQuota::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = HttpExceptionMap::statusCode($e);
                $errors = HttpExceptionMap::normalizeErrors($e);

                $envelope = [
                    'message' => $errors[0]['message'] ?? 'An error occurred.',
                    'errors' => $errors,
                ];

                if (function_exists('tenant') && tenant()) {
                    $envelope['tenant_id'] = tenant('id');
                }

                $correlationId = CorrelationId::current($request);
                if ($correlationId) {
                    $envelope['correlation_id'] = $correlationId;
                }

                return new JsonResponse($envelope, $statusCode);
            }
        });

        $exceptions->report(function (QuotaExceededException $e) {
            Log::warning('Quota exceeded', [
                'metric' => $e->metric,
                'tenant_id' => function_exists('tenant') ? tenant('id') : null,
            ]);
        });
    })->create();
