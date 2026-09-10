<?php

declare(strict_types=1);

return [
    'environment' => env('DLOCAL_ENV', 'sandbox'),
    'login' => env('DLOCAL_LOGIN'),

    /*
     * Public credential used to initialize dLocal.js / Smart Fields in the
     * browser (dlocal('API_KEY')). Defaults to the API login when no
     * dedicated JS key is configured.
     */
    'js_api_key' => env('DLOCAL_JS_API_KEY') ?: env('DLOCAL_LOGIN'),
    'trans_key' => env('DLOCAL_TRANS_KEY'),
    'secret_key' => env('DLOCAL_SECRET_KEY'),
    'webhook_secret' => env('DLOCAL_WEBHOOK_SECRET'),
    'webhook_allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('DLOCAL_WEBHOOK_ALLOWED_IPS', ''))))),
    'retry' => [
        'times' => (int) env('DLOCAL_HTTP_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('DLOCAL_HTTP_RETRY_SLEEP_MS', 100),
    ],
];
