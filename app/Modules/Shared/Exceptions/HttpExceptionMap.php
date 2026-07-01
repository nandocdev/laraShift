<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use App\Modules\Shared\Infrastructure\Exceptions\QuotaExceededException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class HttpExceptionMap
{
    private const array STATUS_MAP = [
        QuotaExceededException::class => 429,
        ModelNotFoundException::class => 404,
        AuthenticationException::class => 401,
        AccessDeniedHttpException::class => 403,
        NotFoundHttpException::class => 404,
    ];

    private const array ERROR_CODES = [
        QuotaExceededException::class => 'quota_exceeded',
        ModelNotFoundException::class => 'resource_not_found',
        AuthenticationException::class => 'unauthenticated',
        AccessDeniedHttpException::class => 'forbidden',
        ValidationException::class => 'validation_error',
    ];

    public static function statusCode(\Throwable $e): int
    {
        foreach (self::STATUS_MAP as $class => $status) {
            if ($e instanceof $class) {
                return $status;
            }
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        if ($e instanceof ValidationException) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $e->getCode() >= 100 && $e->getCode() <= 599
            ? (int) $e->getCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public static function errorCode(\Throwable $e): string
    {
        foreach (self::ERROR_CODES as $class => $code) {
            if ($e instanceof $class) {
                return $code;
            }
        }

        return 'internal_error';
    }

    /**
     * @return array<int, array{message: string, code?: string, field?: string}>
     */
    public static function normalizeErrors(\Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->map(function (array $messages, string $field) {
                return [
                    'message' => $messages[0],
                    'code' => 'validation_error',
                    'field' => $field,
                ];
            })->values()->toArray();
        }

        return [
            [
                'message' => $e->getMessage() ?: 'An unexpected error occurred.',
                'code' => self::errorCode($e),
            ],
        ];
    }
}
