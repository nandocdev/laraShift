<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Domain\Enums;

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
    case LOCATION_CREATED = 'location.created';
    case LOCATION_UPDATED = 'location.updated';
    case LOCATION_DELETED = 'location.deleted';
    case SERVICE_CREATED = 'service.created';
    case SERVICE_UPDATED = 'service.updated';
    case SERVICE_DELETED = 'service.deleted';
    case CATEGORY_CREATED = 'service_category.created';
    case CATEGORY_UPDATED = 'service_category.updated';
    case CATEGORY_DELETED = 'service_category.deleted';
    case RESOURCE_CREATED = 'resource.created';
    case RESOURCE_UPDATED = 'resource.updated';
    case RESOURCE_DELETED = 'resource.deleted';
}
