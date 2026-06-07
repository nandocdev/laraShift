<?php

return [
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
];
