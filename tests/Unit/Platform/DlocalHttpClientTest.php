<?php

declare(strict_types=1);

use App\Modules\Platform\Integrations\Dlocal\Client\DlocalHttpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('transmits the exact JSON body that was signed', function () {
    $captured = null;

    Http::fake([
        'sandbox.dlocal.com/*' => function ($request) use (&$captured) {
            $captured = $request->body();

            return Http::response([
                'id' => 'P-1',
                'order_id' => 'order-1',
                'status' => 'PENDING',
                'amount' => '1.00',
                'currency' => 'USD',
            ]);
        },
    ]);

    $payload = [
        'order_id' => 'order-1',
        'amount' => '1.00',
        'notification_url' => 'http://merchant.com/webhooks/dlocal',
        'payer' => ['name' => 'Test', 'email' => 'test@example.com'],
    ];

    (new DlocalHttpClient(
        baseUrl: 'https://sandbox.dlocal.com',
        login: 'x-login',
        transKey: 'x-trans-key',
        secretKey: 'secret',
    ))->post('/payments', $payload);

    // The transmitted body must be byte-for-byte the string used to build the
    // HMAC signature. If Guzzle re-encoded it (e.g. JSON_UNESCAPED_SLASHES),
    // dLocal would reject the request with "Invalid Auth Token".
    expect($captured)->toBe(json_encode($payload, JSON_THROW_ON_ERROR));
});

it('sends the dLocal authentication headers', function () {
    $headers = null;

    Http::fake([
        'sandbox.dlocal.com/*' => function ($request) use (&$headers) {
            $headers = $request->headers();

            return Http::response([
                'id' => 'P-2',
                'order_id' => 'order-2',
                'status' => 'PENDING',
                'amount' => '1.00',
                'currency' => 'USD',
            ]);
        },
    ]);

    (new DlocalHttpClient(
        baseUrl: 'https://sandbox.dlocal.com',
        login: 'x-login',
        transKey: 'x-trans-key',
        secretKey: 'secret',
    ))->post('/payments', ['order_id' => 'order-2', 'amount' => '1.00']);

    expect($headers['X-Login'][0] ?? null)->toBe('x-login');
    expect($headers['X-Trans-Key'][0] ?? null)->toBe('x-trans-key');
    expect($headers['X-Version'][0] ?? null)->toBe('2.1');
    expect($headers['Authorization'][0] ?? null)->toContain('V2-HMAC-SHA256, Signature:');
    // dLocal requires ISO-8601 UTC with milliseconds + Z.
    expect($headers['X-Date'][0] ?? null)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
});
