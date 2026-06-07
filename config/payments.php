<?php

return [
    'clave' => [
        /*
         * 'production' | 'sandbox' | 'dev'
         */
        'environment' => env('CLAVE_ENVIRONMENT', 'sandbox'),

        /*
         * CCLW API key from PagueLo Fácil dashboard.
         */
        'api_key' => env('CLAVE_API_KEY'),

        /*
         * Secret used to verify inbound webhook signatures (HMAC-SHA256).
         */
        'webhook_secret' => env('CLAVE_WEBHOOK_SECRET'),
    ],
];
