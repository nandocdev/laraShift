<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Actions;

use App\Modules\Tenant\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Defines what support agents can see in tenant audit logs
 * without violating tenant isolation.
 */
final readonly class SupportVisibilityAction
{
    private const array ALLOWED_RESOURCES = [
        'settings',
        'billing',
        'user',
    ];

    private const array SENSITIVE_FIELDS = [
        'password',
        'secret',
        'token',
        'recovery_codes',
    ];

    /**
     * Get audit logs visible to support agents.
     * Excludes sensitive metadata fields.
     */
    public function visibleLogs(string $tenantId, int $limit = 50): array
    {
        $logs = AuditLog::where('tenant_id', $tenantId)
            ->whereIn('resource', self::ALLOWED_RESOURCES)
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();

        return array_map([$this, 'sanitize'], $logs);
    }

    /**
     * Check if a resource type is visible to support.
     */
    public function isVisible(string $resource): bool
    {
        return in_array($resource, self::ALLOWED_RESOURCES, true);
    }

    /**
     * Get the list of resources support can see.
     *
     * @return string[]
     */
    public static function allowedResources(): array
    {
        return self::ALLOWED_RESOURCES;
    }

    /**
     * Sanitize sensitive fields from metadata.
     *
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    private function sanitize(array $log): array
    {
        if (isset($log['metadata'])) {
            $metadata = is_string($log['metadata']) ? json_decode($log['metadata'], true) : (array) $log['metadata'];

            foreach (self::SENSITIVE_FIELDS as $field) {
                foreach ($metadata as $key => $value) {
                    if (str_contains(strtolower($key), $field)) {
                        $metadata[$key] = '[REDACTED]';
                    }
                }
            }

            $log['metadata'] = $metadata;
        }

        return $log;
    }
}
