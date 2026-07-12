<?php

declare(strict_types=1);

return [
    'environment' => env('DLOCAL_ENV', 'sandbox'),
    'login' => env('DLOCAL_LOGIN'),
    'trans_key' => env('DLOCAL_TRANS_KEY'),
    'secret_key' => env('DLOCAL_SECRET_KEY'),
    'webhook_secret' => env('DLOCAL_WEBHOOK_SECRET'),
];
