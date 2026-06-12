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
            resourceId: (string) $event->invitation->id,
            metadata: ['email' => $event->email, 'role_id' => $event->roleId]
        ));
    }

    public function handleUserJoined(TenantUserJoined $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::USER_JOINED,
            resource: 'users',
            resourceId: (string) $event->user->id,
            metadata: ['via_invite_id' => $event->viaInviteId]
        ));
    }

    public function handleUserRevoked(TenantUserRevoked $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::USER_REVOKED,
            resource: 'users',
            resourceId: (string) $event->user->id,
            metadata: ['revoked_by' => $event->revokedBy]
        ));
    }

    public function handleRoleCreated(TenantRoleCreated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::ROLE_CREATED,
            resource: 'roles',
            resourceId: (string) $event->role->id,
            metadata: ['name' => $event->role->name]
        ));
    }

    public function handleRoleUpdated(TenantRoleUpdated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::ROLE_UPDATED,
            resource: 'roles',
            resourceId: (string) $event->role->id,
            metadata: ['changed_permissions' => $event->changedPermissions]
        ));
    }

    public function handleApiKeyCreated(TenantApiKeyCreated $event): void
    {
        $this->recordAuditLog->execute(new AuditLogData(
            action: AuditAction::API_KEY_CREATED,
            resource: 'api_keys',
            resourceId: (string) $event->apiKey->id,
            metadata: [
                'name' => $event->apiKey->name, 
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
            resourceId: (string) $event->apiKey->id,
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
