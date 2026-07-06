<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Listeners;

use App\Modules\Platform\Events\TenantApiKeyCreated;
use App\Modules\Platform\Events\TenantApiKeyRevoked;
use App\Modules\Platform\Events\TenantMfaRequirementChanged;
use App\Modules\Platform\Events\TenantRoleCreated;
use App\Modules\Platform\Events\TenantRoleUpdated;
use App\Modules\Platform\Events\TenantSettingsUpdated;
use App\Modules\Platform\Events\TenantSmtpConfigured;
use App\Modules\Platform\Events\TenantUserInvited;
use App\Modules\Platform\Events\TenantUserJoined;
use App\Modules\Platform\Events\TenantUserRevoked;
use App\Modules\Tenant\Audit\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Audit\DTOs\AuditLogData;
use App\Modules\Tenant\Audit\Enums\AuditAction;
use Illuminate\Events\Dispatcher;

class TenantIdentityEventSubscriber
{
    public function __construct(
        private RecordAuditLogAction $recordAuditLog
    ) {}

    public function handleUserInvited(TenantUserInvited $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::USER_INVITED,
            resource: 'invitations',
            resourceId: $event->invitationId,
            metadata: ['email' => $event->email, 'role_id' => $event->roleId]
        ));
    }

    public function handleUserJoined(TenantUserJoined $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::USER_JOINED,
            resource: 'users',
            resourceId: $event->userId,
            metadata: ['via_invite_id' => $event->viaInviteId]
        ));
    }

    public function handleUserRevoked(TenantUserRevoked $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::USER_REVOKED,
            resource: 'users',
            resourceId: $event->userId,
            metadata: ['revoked_by' => $event->revokedBy]
        ));
    }

    public function handleRoleCreated(TenantRoleCreated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::ROLE_CREATED,
            resource: 'roles',
            resourceId: $event->roleId,
            metadata: ['name' => $event->roleName]
        ));
    }

    public function handleRoleUpdated(TenantRoleUpdated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::ROLE_UPDATED,
            resource: 'roles',
            resourceId: $event->roleId,
            metadata: ['changed_permissions' => $event->changedPermissions]
        ));
    }

    public function handleApiKeyCreated(TenantApiKeyCreated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::API_KEY_CREATED,
            resource: 'api_keys',
            resourceId: $event->keyId,
            metadata: [
                'name' => $event->keyName, 
                'scopes' => $event->scopes,
                'ua' => request()->userAgent()
            ],
            ip: request()->ip()
        ));
    }

    public function handleApiKeyRevoked(TenantApiKeyRevoked $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::API_KEY_REVOKED,
            resource: 'api_keys',
            resourceId: $event->keyId,
            metadata: ['ua' => request()->userAgent()],
            ip: request()->ip()
        ));
    }

    public function handleSettingsUpdated(TenantSettingsUpdated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::SETTINGS_UPDATED,
            resource: 'settings',
            metadata: ['changed_fields' => $event->changedFields]
        ));
    }

    public function handleSmtpConfigured(TenantSmtpConfigured $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::SETTINGS_SMTP_CONFIGURED,
            resource: 'settings',
            metadata: ['from_email' => $event->fromEmail]
        ));
    }

    public function handleMfaRequirementChanged(TenantMfaRequirementChanged $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::SETTINGS_MFA_CHANGED,
            resource: 'settings',
            metadata: ['mfa_required' => $event->mfaRequired]
        ));
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
