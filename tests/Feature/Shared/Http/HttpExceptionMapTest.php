<?php

declare(strict_types=1);

use App\Modules\Shared\Exceptions\HttpExceptionMap;
use App\Modules\Shared\Infrastructure\Exceptions\QuotaExceededException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

test('maps quota exceeded to 429', function () {
    $e = new QuotaExceededException('api_calls');

    expect(HttpExceptionMap::statusCode($e))->toBe(429);
});

test('maps model not found to 404', function () {
    $e = new ModelNotFoundException;

    expect(HttpExceptionMap::statusCode($e))->toBe(404);
});

test('maps authentication to 401', function () {
    $e = new AuthenticationException;

    expect(HttpExceptionMap::statusCode($e))->toBe(401);
});

test('maps access denied to 403', function () {
    $e = new AccessDeniedHttpException;

    expect(HttpExceptionMap::statusCode($e))->toBe(403);
});

test('maps validation to 422', function () {
    $e = ValidationException::withMessages(['email' => 'Required']);

    expect(HttpExceptionMap::statusCode($e))->toBe(422);
});

test('defaults unknown exceptions to 500', function () {
    $e = new RuntimeException('Unexpected');

    expect(HttpExceptionMap::statusCode($e))->toBe(500);
});

test('error code for quota exceeded', function () {
    $e = new QuotaExceededException('api_calls');

    expect(HttpExceptionMap::errorCode($e))->toBe('quota_exceeded');
});

test('error code for model not found', function () {
    $e = new ModelNotFoundException;

    expect(HttpExceptionMap::errorCode($e))->toBe('resource_not_found');
});

test('error code for authentication', function () {
    $e = new AuthenticationException;

    expect(HttpExceptionMap::errorCode($e))->toBe('unauthenticated');
});

test('error code defaults to internal_error', function () {
    $e = new RuntimeException;

    expect(HttpExceptionMap::errorCode($e))->toBe('internal_error');
});

test('normalizes validation errors', function () {
    $e = ValidationException::withMessages([
        'email' => ['The email field is required.'],
        'name' => ['The name field is required.'],
    ]);

    $errors = HttpExceptionMap::normalizeErrors($e);

    expect($errors)->toHaveCount(2);
    expect($errors[0])->toHaveKey('field');
    expect($errors[0])->toHaveKey('code');
});
