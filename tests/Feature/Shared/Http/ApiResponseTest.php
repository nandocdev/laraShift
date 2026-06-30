<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Responses\ApiResponse;

test('success response includes data envelope', function () {
    $response = ApiResponse::success(['key' => 'value']);

    $content = $response->getData(true);

    expect($content)->toHaveKey('data');
    expect($content['data']['key'])->toBe('value');
    expect($content['message'])->toBe('OK');
});

test('success response defaults to 200', function () {
    $response = ApiResponse::success();

    expect($response->getStatusCode())->toBe(200);
});

test('created response defaults to 201', function () {
    $response = ApiResponse::created(['id' => 1]);

    expect($response->getStatusCode())->toBe(201);
    expect($response->getData(true)['message'])->toBe('Created');
});

test('error response includes errors array', function () {
    $response = ApiResponse::error([
        ['message' => 'Validation failed', 'code' => 'validation_error', 'field' => 'email'],
    ]);

    $content = $response->getData(true);

    expect($content)->toHaveKey('errors');
    expect($content['errors'][0]['message'])->toBe('Validation failed');
    expect($response->getStatusCode())->toBe(400);
});

test('error response supports custom status code', function () {
    $response = ApiResponse::error(
        [['message' => 'Not found']],
        message: 'Not Found',
        code: 404,
    );

    expect($response->getStatusCode())->toBe(404);
});

test('response includes meta when provided', function () {
    $response = ApiResponse::success(['id' => 1], meta: ['total' => 10, 'page' => 1]);

    $content = $response->getData(true);

    expect($content['meta']['total'])->toBe(10);
    expect($content['meta']['page'])->toBe(1);
});
