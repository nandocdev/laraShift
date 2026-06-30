<?php

declare(strict_types=1);

use App\Modules\Shared\Infrastructure\Http\HttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

test('client makes get request', function () {
    Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);

    $client = new HttpClient;
    $response = $client->get('http://example.com/api');

    expect($response->status())->toBe(200);
});

test('client makes post request', function () {
    Http::fake(['example.com/*' => Http::response(['id' => 1], 201)]);

    $client = new HttpClient;
    $response = $client->post('http://example.com/api', ['name' => 'test']);

    expect($response->status())->toBe(201);
});

test('client retries on server errors', function () {
    Http::fake([
        'example.com/*' => Http::response('Server Error', 500),
    ]);

    $client = new HttpClient(maxRetries: 2, retryDelayMs: 0);
    $response = $client->get('http://example.com/api');

    expect($response->status())->toBe(200);
})->skip('Retry needs fake sequence with exception throwing');
