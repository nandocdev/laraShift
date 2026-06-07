<?php

return [
    'default' => env('PAYMENTS_DEFAULT', 'clave'),

    'clave' => [
        /*
         * 'production' | 'sandbox' | 'dev'
         */
        'environment' => env('PAGUELOFACIL_ENV', env('APP_ENV') === 'production' ? 'production' : 'sandbox'),

        /*
         * CCLW API key from PagueLo Fácil dashboard.
         */
        'api_key' => env('PAGUELOFACIL_API'),

        /*
         * Commercial code (CCLW) for LinkDeamon or older APIs.
         */
        'cclw' => env('PAGUELOFACIL_CCLW'),

        /*
         * Secret used to verify inbound webhook signatures (HMAC-SHA256).
         */
        'webhook_secret' => env('PAGUELOFACIL_WEBHOOK_SECRET'),
    ],

    'dlocal' => [
        /*
         * 'production' | 'sandbox'
         */
        'environment' => env('DLOCAL_ENV', env('APP_ENV') === 'production' ? 'production' : 'sandbox'),

        /*
         * X-Login from dLocal Go dashboard.
         */
        'login' => env('DLOCAL_LOGIN'),

        /*
         * X-Trans-Key from dLocal Go dashboard.
         */
        'trans_key' => env('DLOCAL_TRANS_KEY'),

        /*
         * Secret Key used for API calls and signing.
         */
        'secret_key' => env('DLOCAL_SECRET_KEY'),

        /*
         * Secret used to verify inbound webhook signatures.
         */
        'webhook_secret' => env('DLOCAL_WEBHOOK_SECRET'),
    ],
];
