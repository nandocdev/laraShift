<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Fraud Score Threshold
     |--------------------------------------------------------------------------
     |
     | Tenants whose fraud score equals or exceeds this value during registration
     | are placed in 'quarantine' status instead of 'active'.
     |
     */
    'quarantine_threshold' => (int) env('FRAUD_QUARANTINE_THRESHOLD', 80),

    /*
     |--------------------------------------------------------------------------
     | SecOps Alert Email
     |--------------------------------------------------------------------------
     |
     | Email address that receives the quarantine notification when a tenant
     | is flagged. Leave null to disable email alerts.
     |
     */
    'secops_email' => env('FRAUD_SECOPS_EMAIL'),

    /*
     |--------------------------------------------------------------------------
     | Disposable Email Domains (additional)
     |--------------------------------------------------------------------------
     |
     | Extend the built-in list in FraudScoringAction with project-specific
     | domains. Comma-separated in the env var, or as a PHP array here.
     |
     */
    'disposable_domains' => array_filter(
        explode(',', (string) env('FRAUD_DISPOSABLE_DOMAINS', ''))
    ),

    /*
     |--------------------------------------------------------------------------
     | Blocked IP Prefixes
     |--------------------------------------------------------------------------
     |
     | CIDR-prefix notation not supported — use exact string prefixes only.
     | Example: ['185.220.', '192.42.116.'] covers known Tor exit ranges.
     |
     */
    'blocked_ip_prefixes' => array_filter(
        explode(',', (string) env('FRAUD_BLOCKED_IP_PREFIXES', ''))
    ),
];
