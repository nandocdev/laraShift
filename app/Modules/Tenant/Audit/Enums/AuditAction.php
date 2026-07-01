<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Enums;

enum AuditAction: string
{
    case AUTH_LOGIN = 'auth.login';
    case AUTH_LOGOUT = 'auth.logout';
    case USER_INVITED = 'user.invited';
    case USER_JOINED = 'user.joined';
    case USER_REVOKED = 'user.revoked';
    case USER_DELETED = 'user.deleted';
    case ROLE_CREATED = 'role.created';
    case ROLE_UPDATED = 'role.updated';
    case API_KEY_CREATED = 'api_key.created';
    case API_KEY_REVOKED = 'api_key.revoked';
    case SETTINGS_UPDATED = 'settings.updated';
    case SETTINGS_SMTP_CONFIGURED = 'settings.smtp_configured';
    case SETTINGS_MFA_CHANGED = 'settings.mfa_requirement_changed';
    case EXPORT_STARTED = 'export.initiated';

    public function severity(): string
    {
        return match ($this) {
            self::AUTH_LOGIN, self::AUTH_LOGOUT => 'CRITICAL',
            self::USER_INVITED,
            self::USER_JOINED,
            self::USER_REVOKED,
            self::USER_DELETED,
            self::ROLE_CREATED,
            self::ROLE_UPDATED,
            self::API_KEY_CREATED,
            self::API_KEY_REVOKED,
            self::SETTINGS_SMTP_CONFIGURED,
            self::SETTINGS_MFA_CHANGED => 'HIGH',
            self::SETTINGS_UPDATED => 'MEDIUM',
            self::EXPORT_STARTED => 'LOW',
        };
    }

    public static function visibleForSupport(): array
    {
        return array_filter(self::cases(), fn (self $action) => in_array($action->severity(), ['CRITICAL', 'HIGH'], true));
    }
}
