<?php

declare(strict_types=1);

return [
    'free' => [
        'id' => 'free',
        'name' => 'Free',
        'price' => 0,
        'currency' => 'usd',
        'interval' => 'month',
        'features' => [
            '1 Branch',
            '3 Staff members',
            '100 Bookings / month',
        ],
        'quotas' => [
            'branches' => 1,
            'staff' => 3,
            'bookings' => 100,
            'rate_limit_rpm' => 60,
        ],
    ],
    'pro' => [
        'id' => 'pro',
        'name' => 'Pro',
        'price' => 2900, // In cents
        'currency' => 'usd',
        'interval' => 'month',
        'stripe_id' => env('STRIPE_PRO_PLAN_ID'),
        'features' => [
            '3 Branches',
            '10 Staff members',
            'Unlimited Bookings',
            'Email Notifications',
        ],
        'quotas' => [
            'branches' => 3,
            'staff' => 10,
            'bookings' => -1, // Unlimited
            'rate_limit_rpm' => 300,
        ],
    ],
    'enterprise' => [
        'id' => 'enterprise',
        'name' => 'Enterprise',
        'price' => 9900,
        'currency' => 'usd',
        'interval' => 'month',
        'stripe_id' => env('STRIPE_ENTERPRISE_PLAN_ID'),
        'features' => [
            'Unlimited Branches',
            'Unlimited Staff members',
            'Unlimited Bookings',
            'Priority Support',
            'Custom Domain',
        ],
        'quotas' => [
            'branches' => -1,
            'staff' => -1,
            'bookings' => -1,
            'rate_limit_rpm' => 1000,
        ],
    ],
];
