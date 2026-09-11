<?php

return [
    'environment' => env('DLOCAL_ENVIRONMENT', 'sandbox'),
    'login' => env('DLOCAL_LOGIN', ''),
    'trans_key' => env('DLOCAL_TRANS_KEY', ''),
    'secret_key' => env('DLOCAL_SECRET_KEY', ''),
    'webhook_secret' => env('DLOCAL_WEBHOOK_SECRET', ''),
    // Public browser credential for dLocal.js / Smart Fields. Never the API login.
    'js_api_key' => env('DLOCAL_JS_API_KEY', ''),
    'country_default' => env('DLOCAL_COUNTRY_DEFAULT', 'AR'),
];
