<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Services;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class TenantStateMachine
{
    private const array VALID_TRANSITIONS = [
        'provisioning' => ['active', 'failed'],
        'active' => ['suspended', 'archived', 'maintenance'],
        'suspended' => ['active', 'archived'],
        'archived' => [],
        'maintenance' => ['active'],
        'failed' => ['active'],
    ];

    public function canTransition(Tenant $tenant, string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$tenant->status] ?? [], true);
    }

    public function transition(Tenant $tenant, string $newStatus, ?string $reason = null): bool
    {
        if (! $this->canTransition($tenant, $newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition tenant from '{$tenant->status}' to '{$newStatus}'."
            );
        }

        $oldStatus = $tenant->status;

        $updates = ['status' => $newStatus];

        match ($newStatus) {
            'suspended' => $updates['suspended_at'] = now(),
            'archived' => $updates['archived_at'] = now(),
            'active' => $updates['suspended_at'] = null,
            default => null,
        };

        $tenant->update($updates);

        activity('billing')
            ->performedOn($tenant)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ])
            ->log("tenant_status_changed:{$oldStatus}->{$newStatus}");

        return true;
    }

    /**
     * @return array<string, string[]>
     */
    public static function getAllowedTransitions(): array
    {
        return self::VALID_TRANSITIONS;
    }

    public static function isTerminal(string $status): bool
    {
        return $status === 'archived';
    }

    public static function isActive(string $status): bool
    {
        return $status === 'active';
    }

    public static function isSuspended(string $status): bool
    {
        return $status === 'suspended';
    }
}
