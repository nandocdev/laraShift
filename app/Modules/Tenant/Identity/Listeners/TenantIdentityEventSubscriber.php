<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Listeners;

use App\Modules\Shared\Events\TenantApiKeyCreated;
use App\Modules\Shared\Events\TenantApiKeyRevoked;
use App\Modules\Shared\Events\TenantMfaRequirementChanged;
use App\Modules\Shared\Events\TenantRoleCreated;
use App\Modules\Shared\Events\TenantRoleUpdated;
use App\Modules\Shared\Events\TenantSettingsUpdated;
use App\Modules\Shared\Events\TenantSmtpConfigured;
use App\Modules\Shared\Events\TenantUserInvited;
use App\Modules\Shared\Events\TenantUserJoined;
use App\Modules\Shared\Events\TenantUserRevoked;
use App\Modules\Tenant\Audit\Actions\RecordAuditLogAction;
use Illuminate\Events\Dispatcher;

class TenantIdentityEventSubscriber
{
    public function __construct(
        private RecordAuditLogAction $recordAuditLog
    ) {}

    public function handleUserInvited(TenantUserInvited $event): void
    {
        $this->recordAuditLog->execute(
            action: 'user.invited',
            resource: 'invitations',
            resourceId: (string) $event->invitation->id,
            metadata: ['email' => $event->email, 'role_id' => $event->roleId]
        );
    }

    public function handleUserJoined(TenantUserJoined $event): void
    {
        $this->recordAuditLog->execute(
            action: 'user.joined',
            resource: 'users',
            resourceId: (string) $event->user->id,
            metadata: ['via_invite_id' => $event->viaInviteId]
        );
    }

    public function handleUserRevoked(TenantUserRevoked $event): void
    {
        $this->recordAuditLog->execute(
            action: 'user.revoked',
            resource: 'users',
            resourceId: (string) $event->user->id,
            metadata: ['revoked_by' => $event->revokedBy]
        );
    }

    public function handleRoleCreated(TenantRoleCreated $event): void
    {
        $this->recordAuditLog->execute(
            action: 'role.created',
            resource: 'roles',
            resourceId: (string) $event->role->id,
            metadata: ['name' => $event->role->name]
        );
    }

    public function handleRoleUpdated(TenantRoleUpdated $event): void
    {
        $this->recordAuditLog->execute(
            action: 'role.updated',
            resource: 'roles',
            resourceId: (string) $event->role->id,
            metadata: ['changed_permissions' => $event->changedPermissions]
        );
    }

    public function handleApiKeyCreated(TenantApiKeyCreated $event): void
    {
        $this->recordAuditLog->execute(
            action: 'api_key.created',
            resource: 'api_keys',
            resourceId: (string) $event->apiKey->id,
            metadata: ['name' => $event->apiKey->name, 'scopes' => $event->scopes]
        );
    }

    public function handleApiKeyRevoked(TenantApiKeyRevoked $event): void
    {
        $this->recordAuditLog->execute(
            action: 'api_key.revoked',
            resource: 'api_keys',
            resourceId: (string) $event->apiKey->id
        );
    }

    public function handleSettingsUpdated(TenantSettingsUpdated $event): void
    {
        $this->recordAuditLog->execute(
            action: 'settings.updated',
            resource: 'settings',
            metadata: ['changed_fields' => $event->changedFields]
        );
    }

    public function handleSmtpConfigured(TenantSmtpConfigured $event): void
    {
        $this->recordAuditLog->execute(
            action: 'settings.smtp_configured',
            resource: 'settings',
            metadata: ['from_email' => $event->fromEmail]
        );
    }

    public function handleMfaRequirementChanged(TenantMfaRequirementChanged $event): void
    {
        $this->recordAuditLog->execute(
            action: 'settings.mfa_requirement_changed',
            resource: 'settings',
            metadata: ['mfa_required' => $event->mfaRequired]
        );
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TenantUserInvited::class => 'handleUserInvited',
            TenantUserJoined::class => 'handleUserJoined',
            TenantUserRevoked::class => 'handleUserRevoked',
            TenantRoleCreated::class => 'handleRoleCreated',
            TenantRoleUpdated::class => 'handleRoleUpdated',
            TenantApiKeyCreated::class => 'handleApiKeyCreated',
            TenantApiKeyRevoked::class => 'handleApiKeyRevoked',
            TenantSettingsUpdated::class => 'handleSettingsUpdated',
            TenantSmtpConfigured::class => 'handleSmtpConfigured',
            TenantMfaRequirementChanged::class => 'handleMfaRequirementChanged',
        ];
    }
}
