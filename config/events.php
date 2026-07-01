<?php

declare(strict_types=1);
use App\Modules\Shared\Events\PaymentCompleted;
use App\Modules\Shared\Events\PaymentFailed;
use App\Modules\Shared\Events\PaymentSucceeded;
use App\Modules\Shared\Events\SubscriptionCancelled;
use App\Modules\Shared\Events\SubscriptionCreated;
use App\Modules\Shared\Events\SubscriptionUpdated;
use App\Modules\Shared\Events\TenantApiKeyCreated;
use App\Modules\Shared\Events\TenantApiKeyRevoked;
use App\Modules\Shared\Events\TenantMfaRequirementChanged;
use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Shared\Events\TenantReactivatedAfterPayment;
use App\Modules\Shared\Events\TenantRoleCreated;
use App\Modules\Shared\Events\TenantRoleUpdated;
use App\Modules\Shared\Events\TenantSettingsUpdated;
use App\Modules\Shared\Events\TenantSmtpConfigured;
use App\Modules\Shared\Events\TenantSuspendedByDunning;
use App\Modules\Shared\Events\TenantUserInvited;
use App\Modules\Shared\Events\TenantUserJoined;
use App\Modules\Shared\Events\TenantUserRevoked;

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
        'tenant_provisioned' => TenantProvisioned::class,
        'subscription_created' => SubscriptionCreated::class,
        'subscription_updated' => SubscriptionUpdated::class,
        'subscription_cancelled' => SubscriptionCancelled::class,
        'payment_completed' => PaymentCompleted::class,
        'payment_succeeded' => PaymentSucceeded::class,
        'payment_failed' => PaymentFailed::class,
        'tenant_api_key_created' => TenantApiKeyCreated::class,
        'tenant_api_key_revoked' => TenantApiKeyRevoked::class,
        'tenant_mfa_requirement_changed' => TenantMfaRequirementChanged::class,
        'tenant_reactivated_after_payment' => TenantReactivatedAfterPayment::class,
        'tenant_role_created' => TenantRoleCreated::class,
        'tenant_role_updated' => TenantRoleUpdated::class,
        'tenant_settings_updated' => TenantSettingsUpdated::class,
        'tenant_smtp_configured' => TenantSmtpConfigured::class,
        'tenant_suspended_by_dunning' => TenantSuspendedByDunning::class,
        'tenant_user_invited' => TenantUserInvited::class,
        'tenant_user_joined' => TenantUserJoined::class,
        'tenant_user_revoked' => TenantUserRevoked::class,
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
