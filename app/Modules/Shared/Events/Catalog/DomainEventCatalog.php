<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Catalog;

/**
 * Registry of all domain events in the system.
 *
 * Each entry documents the event type string, version, description,
 * and the payload fields. Used by the outbox worker to map event_type
 * strings to their PHP classes and by developers as a single source of truth.
 */
final class DomainEventCatalog
{
    /**
     * @return array<int, array{type: string, class: string, version: int, description: string}>
     */
    public static function all(): array
    {
        return [
            // === Tenant Lifecycle ===
            [
                'type' => 'tenant_provisioned',
                'class' => \App\Modules\Shared\Events\TenantProvisioned::class,
                'version' => 1,
                'description' => 'Emitted when a new tenant has been fully provisioned.',
            ],
            [
                'type' => 'tenant_suspended_by_dunning',
                'class' => \App\Modules\Shared\Events\TenantSuspendedByDunning::class,
                'version' => 1,
                'description' => 'Emitted when a tenant is suspended due to failed payment dunning.',
            ],
            [
                'type' => 'tenant_reactivated_after_payment',
                'class' => \App\Modules\Shared\Events\TenantReactivatedAfterPayment::class,
                'version' => 1,
                'description' => 'Emitted when a tenant is reactivated after a successful payment following suspension.',
            ],

            // === Subscription ===
            [
                'type' => 'subscription_created',
                'class' => \App\Modules\Shared\Events\SubscriptionCreated::class,
                'version' => 1,
                'description' => 'Emitted when a new subscription is created for a tenant.',
            ],
            [
                'type' => 'subscription_updated',
                'class' => \App\Modules\Shared\Events\SubscriptionUpdated::class,
                'version' => 1,
                'description' => 'Emitted when a subscription is updated (plan change, etc.).',
            ],
            [
                'type' => 'subscription_cancelled',
                'class' => \App\Modules\Shared\Events\SubscriptionCancelled::class,
                'version' => 1,
                'description' => 'Emitted when a subscription is cancelled.',
            ],

            // === Payments ===
            [
                'type' => 'payment_completed',
                'class' => \App\Modules\Shared\Events\PaymentCompleted::class,
                'version' => 1,
                'description' => 'Emitted when a payment completes successfully.',
            ],
            [
                'type' => 'payment_succeeded',
                'class' => \App\Modules\Shared\Events\PaymentSucceeded::class,
                'version' => 1,
                'description' => 'Emitted when a payment is confirmed as succeeded by the gateway.',
            ],
            [
                'type' => 'payment_failed',
                'class' => \App\Modules\Shared\Events\PaymentFailed::class,
                'version' => 1,
                'description' => 'Emitted when a payment fails.',
            ],

            // === Tenant Identity ===
            [
                'type' => 'tenant_user_invited',
                'class' => \App\Modules\Shared\Events\TenantUserInvited::class,
                'version' => 1,
                'description' => 'Emitted when a user is invited to join a tenant.',
            ],
            [
                'type' => 'tenant_user_joined',
                'class' => \App\Modules\Shared\Events\TenantUserJoined::class,
                'version' => 1,
                'description' => 'Emitted when an invited user accepts and joins the tenant.',
            ],
            [
                'type' => 'tenant_user_revoked',
                'class' => \App\Modules\Shared\Events\TenantUserRevoked::class,
                'version' => 1,
                'description' => 'Emitted when a user is revoked from a tenant.',
            ],
            [
                'type' => 'tenant_role_created',
                'class' => \App\Modules\Shared\Events\TenantRoleCreated::class,
                'version' => 1,
                'description' => 'Emitted when a new role is created in a tenant.',
            ],
            [
                'type' => 'tenant_role_updated',
                'class' => \App\Modules\Shared\Events\TenantRoleUpdated::class,
                'version' => 1,
                'description' => 'Emitted when a role is updated.',
            ],

            // === Tenant Settings & Security ===
            [
                'type' => 'tenant_settings_updated',
                'class' => \App\Modules\Shared\Events\TenantSettingsUpdated::class,
                'version' => 1,
                'description' => 'Emitted when tenant settings are updated.',
            ],
            [
                'type' => 'tenant_smtp_configured',
                'class' => \App\Modules\Shared\Events\TenantSmtpConfigured::class,
                'version' => 1,
                'description' => 'Emitted when SMTP configuration is updated for a tenant.',
            ],
            [
                'type' => 'tenant_mfa_requirement_changed',
                'class' => \App\Modules\Shared\Events\TenantMfaRequirementChanged::class,
                'version' => 1,
                'description' => 'Emitted when a tenant\'s MFA requirement policy changes.',
            ],

            // === API Keys ===
            [
                'type' => 'tenant_api_key_created',
                'class' => \App\Modules\Shared\Events\TenantApiKeyCreated::class,
                'version' => 1,
                'description' => 'Emitted when a new API key is created for a tenant.',
            ],
            [
                'type' => 'tenant_api_key_revoked',
                'class' => \App\Modules\Shared\Events\TenantApiKeyRevoked::class,
                'version' => 1,
                'description' => 'Emitted when an API key is revoked.',
            ],
        ];
    }

    /**
     * Get a single event definition by type string.
     *
     * @return array{type: string, class: string, version: int, description: string}|null
     */
    public static function get(string $type): ?array
    {
        foreach (self::all() as $event) {
            if ($event['type'] === $type) {
                return $event;
            }
        }

        return null;
    }

    /**
     * The config map (event_type => FQCN) used by the outbox worker.
     *
     * @return array<string, string>
     */
    public static function toConfigMap(): array
    {
        $map = [];

        foreach (self::all() as $event) {
            $key = str_replace('.', '_', $event['type']);
            $map[$key] = $event['class'];
        }

        return $map;
    }
}
