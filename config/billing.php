<?php

return [
    'environment' => env('BILLING_ENV', env('APP_ENV') === 'production' ? 'production' : 'sandbox'),

    'dlocal' => [
        'login' => env('DLOCAL_LOGIN', ''),
        'trans_key' => env('DLOCAL_TRANS_KEY', ''),
        'secret_key' => env('DLOCAL_SECRET_KEY', ''),
        'webhook_secret' => env('DLOCAL_WEBHOOK_SECRET', ''),
    ],

    'paguelofacil' => [
        'cclw' => env('PAGUELOFACIL_CCLW', ''),
        'api_token' => env('PAGUELOFACIL_API', ''),
        'base_url' => env('PAGUELOFACIL_ENV', env('APP_ENV') === 'production' ? 'https://secure.paguelofacil.com' : 'https://sandbox.paguelofacil.com'),
    ],
];
