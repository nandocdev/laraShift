<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Catalog;

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
                'class' => TenantProvisioned::class,
                'version' => 1,
                'description' => 'Emitted when a new tenant has been fully provisioned.',
            ],
            [
                'type' => 'tenant_suspended_by_dunning',
                'class' => TenantSuspendedByDunning::class,
                'version' => 1,
                'description' => 'Emitted when a tenant is suspended due to failed payment dunning.',
            ],
            [
                'type' => 'tenant_reactivated_after_payment',
                'class' => TenantReactivatedAfterPayment::class,
                'version' => 1,
                'description' => 'Emitted when a tenant is reactivated after a successful payment following suspension.',
            ],

            // === Subscription ===
            [
                'type' => 'subscription_created',
                'class' => SubscriptionCreated::class,
                'version' => 1,
                'description' => 'Emitted when a new subscription is created for a tenant.',
            ],
            [
                'type' => 'subscription_updated',
                'class' => SubscriptionUpdated::class,
                'version' => 1,
                'description' => 'Emitted when a subscription is updated (plan change, etc.).',
            ],
            [
                'type' => 'subscription_cancelled',
                'class' => SubscriptionCancelled::class,
                'version' => 1,
                'description' => 'Emitted when a subscription is cancelled.',
            ],

            // === Payments ===
            [
                'type' => 'payment_completed',
                'class' => PaymentCompleted::class,
                'version' => 1,
                'description' => 'Emitted when a payment completes successfully.',
            ],
            [
                'type' => 'payment_succeeded',
                'class' => PaymentSucceeded::class,
                'version' => 1,
                'description' => 'Emitted when a payment is confirmed as succeeded by the gateway.',
            ],
            [
                'type' => 'payment_failed',
                'class' => PaymentFailed::class,
                'version' => 1,
                'description' => 'Emitted when a payment fails.',
            ],

            // === Tenant Identity ===
            [
                'type' => 'tenant_user_invited',
                'class' => TenantUserInvited::class,
                'version' => 1,
                'description' => 'Emitted when a user is invited to join a tenant.',
            ],
            [
                'type' => 'tenant_user_joined',
                'class' => TenantUserJoined::class,
                'version' => 1,
                'description' => 'Emitted when an invited user accepts and joins the tenant.',
            ],
            [
                'type' => 'tenant_user_revoked',
                'class' => TenantUserRevoked::class,
                'version' => 1,
                'description' => 'Emitted when a user is revoked from a tenant.',
            ],
            [
                'type' => 'tenant_role_created',
                'class' => TenantRoleCreated::class,
                'version' => 1,
                'description' => 'Emitted when a new role is created in a tenant.',
            ],
            [
                'type' => 'tenant_role_updated',
                'class' => TenantRoleUpdated::class,
                'version' => 1,
                'description' => 'Emitted when a role is updated.',
            ],

            // === Tenant Settings & Security ===
            [
                'type' => 'tenant_settings_updated',
                'class' => TenantSettingsUpdated::class,
                'version' => 1,
                'description' => 'Emitted when tenant settings are updated.',
            ],
            [
                'type' => 'tenant_smtp_configured',
                'class' => TenantSmtpConfigured::class,
                'version' => 1,
                'description' => 'Emitted when SMTP configuration is updated for a tenant.',
            ],
            [
                'type' => 'tenant_mfa_requirement_changed',
                'class' => TenantMfaRequirementChanged::class,
                'version' => 1,
                'description' => 'Emitted when a tenant\'s MFA requirement policy changes.',
            ],

            // === API Keys ===
            [
                'type' => 'tenant_api_key_created',
                'class' => TenantApiKeyCreated::class,
                'version' => 1,
                'description' => 'Emitted when a new API key is created for a tenant.',
            ],
            [
                'type' => 'tenant_api_key_revoked',
                'class' => TenantApiKeyRevoked::class,
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
