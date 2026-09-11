<?php

return [
    'merchant_id' => env('CLAVE_MERCHANT_ID', ''),
    'secret' => env('CLAVE_SECRET', ''),
    'webhook_secret' => env('CLAVE_WEBHOOK_SECRET', ''),
    'environment' => env('CLAVE_ENVIRONMENT', 'sandbox'),
];
