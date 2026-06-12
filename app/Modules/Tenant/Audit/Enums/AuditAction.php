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
}
