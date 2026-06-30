<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Enums;

/**
 * Catalog of all auditable events with severity levels.
 */
final class AuditEventCatalog
{
    /**
     * @return array<int, array{action: string, severity: string, description: string, resource: string}>
     */
    public static function all(): array
    {
        return [
            [
                'action' => 'auth.login',
                'severity' => 'info',
                'description' => 'User logged in',
                'resource' => 'user',
            ],
            [
                'action' => 'auth.logout',
                'severity' => 'info',
                'description' => 'User logged out',
                'resource' => 'user',
            ],
            [
                'action' => 'user.invited',
                'severity' => 'notice',
                'description' => 'User was invited to join the tenant',
                'resource' => 'user',
            ],
            [
                'action' => 'user.joined',
                'severity' => 'notice',
                'description' => 'Invited user accepted and joined',
                'resource' => 'user',
            ],
            [
                'action' => 'user.revoked',
                'severity' => 'warning',
                'description' => 'User access was revoked',
                'resource' => 'user',
            ],
            [
                'action' => 'user.deleted',
                'severity' => 'warning',
                'description' => 'User was deleted',
                'resource' => 'user',
            ],
            [
                'action' => 'role.created',
                'severity' => 'notice',
                'description' => 'New role was created',
                'resource' => 'role',
            ],
            [
                'action' => 'role.updated',
                'severity' => 'notice',
                'description' => 'Role permissions were updated',
                'resource' => 'role',
            ],
            [
                'action' => 'api_key.created',
                'severity' => 'notice',
                'description' => 'API key was created',
                'resource' => 'api_key',
            ],
            [
                'action' => 'api_key.revoked',
                'severity' => 'warning',
                'description' => 'API key was revoked',
                'resource' => 'api_key',
            ],
            [
                'action' => 'settings.updated',
                'severity' => 'notice',
                'description' => 'Tenant settings were updated',
                'resource' => 'settings',
            ],
            [
                'action' => 'settings.smtp_configured',
                'severity' => 'notice',
                'description' => 'SMTP configuration was updated',
                'resource' => 'settings',
            ],
            [
                'action' => 'settings.mfa_requirement_changed',
                'severity' => 'warning',
                'description' => 'MFA requirement policy changed',
                'resource' => 'settings',
            ],
            [
                'action' => 'export.initiated',
                'severity' => 'info',
                'description' => 'Data export was initiated',
                'resource' => 'data',
            ],
        ];
    }

    /**
     * Get events by severity level.
     *
     * @return array<int, array{action: string, severity: string, description: string, resource: string}>
     */
    public static function bySeverity(string $severity): array
    {
        return array_values(array_filter(self::all(), fn ($e) => $e['severity'] === $severity));
    }

    public static function severity(string $action): string
    {
        foreach (self::all() as $event) {
            if ($event['action'] === $action) {
                return $event['severity'];
            }
        }

        return 'info';
    }
}
