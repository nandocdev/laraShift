<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Product catalog registry
|--------------------------------------------------------------------------
|
| Features and quota metrics the platform knows how to offer on plans.
| This is the extension point for products built on the boilerplate:
| a product exposes its own capabilities here (or merges them from its
| own config file) WITHOUT modifying core code. Plan checks
| (hasFeature / QuotaManager) work with any string key; this file only
| controls which keys ManagePlans offers in the UI.
|
| Labels are plain strings so products can ship their own wording;
| views translate them with __() at render time.
|
*/

return [
    'features' => [
        'basic_dashboard' => ['label' => 'Basic dashboard'],
        'community_support' => ['label' => 'Community support'],
        'priority_support' => ['label' => 'Priority support'],
        'api_access' => ['label' => 'API access'],
    ],

    'quotas' => [
        'bookings' => ['label' => 'Bookings per month'],
        'invitations' => ['label' => 'Team invitations per month'],
        'api_keys' => ['label' => 'API keys'],
    ],
];
