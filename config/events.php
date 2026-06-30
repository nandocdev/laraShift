<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Event Map
    |--------------------------------------------------------------------------
    |
    | Maps event_type strings to their FQCN.
    | Used by the outbox worker to reconstruct events.
    |
    */
    'map' => [
        'tenant_provisioned' => \App\Modules\Shared\Events\TenantProvisioned::class,
        'subscription_created' => \App\Modules\Shared\Events\SubscriptionCreated::class,
        'subscription_updated' => \App\Modules\Shared\Events\SubscriptionUpdated::class,
        'subscription_cancelled' => \App\Modules\Shared\Events\SubscriptionCancelled::class,
        'payment_completed' => \App\Modules\Shared\Events\PaymentCompleted::class,
        'payment_succeeded' => \App\Modules\Shared\Events\PaymentSucceeded::class,
        'payment_failed' => \App\Modules\Shared\Events\PaymentFailed::class,
        'tenant_api_key_created' => \App\Modules\Shared\Events\TenantApiKeyCreated::class,
        'tenant_api_key_revoked' => \App\Modules\Shared\Events\TenantApiKeyRevoked::class,
        'tenant_mfa_requirement_changed' => \App\Modules\Shared\Events\TenantMfaRequirementChanged::class,
        'tenant_reactivated_after_payment' => \App\Modules\Shared\Events\TenantReactivatedAfterPayment::class,
        'tenant_role_created' => \App\Modules\Shared\Events\TenantRoleCreated::class,
        'tenant_role_updated' => \App\Modules\Shared\Events\TenantRoleUpdated::class,
        'tenant_settings_updated' => \App\Modules\Shared\Events\TenantSettingsUpdated::class,
        'tenant_smtp_configured' => \App\Modules\Shared\Events\TenantSmtpConfigured::class,
        'tenant_suspended_by_dunning' => \App\Modules\Shared\Events\TenantSuspendedByDunning::class,
        'tenant_user_invited' => \App\Modules\Shared\Events\TenantUserInvited::class,
        'tenant_user_joined' => \App\Modules\Shared\Events\TenantUserJoined::class,
        'tenant_user_revoked' => \App\Modules\Shared\Events\TenantUserRevoked::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbox Settings
    |--------------------------------------------------------------------------
    */
    'outbox' => [
        'enabled' => env('OUTBOX_ENABLED', true),
        'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 50),
        'max_retries' => (int) env('OUTBOX_MAX_RETRIES', 5),
        'publish_interval' => (int) env('OUTBOX_PUBLISH_INTERVAL', 1), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Dead Letter Queue Settings
    |--------------------------------------------------------------------------
    */
    'dlq' => [
        'enabled' => env('DLQ_ENABLED', true),
        'batch_size' => (int) env('DLQ_BATCH_SIZE', 20),
        'max_retries' => (int) env('DLQ_MAX_RETRIES', 5),
        'retry_interval' => (int) env('DLQ_RETRY_INTERVAL', 1), // minutes
    ],
];
