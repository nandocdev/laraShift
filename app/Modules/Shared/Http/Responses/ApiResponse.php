<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class ApiResponse
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed> $meta
     */
    public static function success(
        ?array $data = null,
        string $message = 'OK',
        int $code = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        return self::envelope($data, null, $message, $code, $meta);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed> $meta
     */
    public static function created(
        ?array $data = null,
        string $message = 'Created',
        array $meta = [],
    ): JsonResponse {
        return self::envelope($data, null, $message, Response::HTTP_CREATED, $meta);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed> $meta
     */
    public static function noContent(
        ?array $data = null,
        string $message = 'No Content',
        array $meta = [],
    ): JsonResponse {
        return self::envelope($data, null, $message, Response::HTTP_NO_CONTENT, $meta);
    }

    /**
     * @param array<int, array{message: string, code?: string, field?: string}> $errors
     * @param array<string, mixed> $meta
     */
    public static function error(
        array $errors,
        string $message = 'Error',
        int $code = Response::HTTP_BAD_REQUEST,
        array $meta = [],
    ): JsonResponse {
        return self::envelope(null, $errors, $message, $code, $meta);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<int, array{message: string, code?: string, field?: string}>|null $errors
     * @param array<string, mixed> $meta
     */
    private static function envelope(
        ?array $data = null,
        ?array $errors = null,
        string $message = '',
        int $code = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        $envelope = [
            'message' => $message,
        ];

        if ($data !== null) {
            $envelope['data'] = $data;
        }

        if ($errors !== null) {
            $envelope['errors'] = $errors;
        }

        if (! empty($meta)) {
            $envelope['meta'] = $meta;
        }

        if (function_exists('tenant') && tenant()) {
            $envelope['tenant_id'] = tenant('id');
        }

        return response()->json($envelope, $code);
    }
}
