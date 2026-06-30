<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Services;

use App\Modules\Central\Billing\Notifications\PaymentFailedNotification;
use App\Modules\Central\Billing\Notifications\TenantSuspendedNotification;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\TenantSuspendedByDunning;
use Illuminate\Support\Facades\Log;

final readonly class DunningEngine
{
    private const DUNNING_SCHEDULE = [
        ['day' => 0, 'action' => 'notify'],
        ['day' => 3, 'action' => 'notify'],
        ['day' => 7, 'action' => 'notify'],
        ['day' => 10, 'action' => 'notify_warning'],
        ['day' => 14, 'action' => 'suspend'],
    ];

    private const GRACE_PERIOD_DAYS = 3;

    /**
     * Process the dunning cycle for a tenant.
     * Returns the action taken.
     */
    public function process(Tenant $tenant, int $failedAttempts): string
    {
        $graceEnd = now()->subDays(self::GRACE_PERIOD_DAYS);

        if ($tenant->suspended_at && $tenant->suspended_at->lte($graceEnd)) {
            return 'already_suspended';
        }

        foreach (self::DUNNING_SCHEDULE as $step) {
            if ($failedAttempts === ($step['day'] + 1)) {
                return $this->executeStep($tenant, $step, $failedAttempts);
            }
        }

        if ($failedAttempts > count(self::DUNNING_SCHEDULE)) {
            return $this->suspend($tenant);
        }

        return 'no_action';
    }

    /**
     * Get the next dunning action date based on failure count.
     */
    public function nextActionDate(int $failedAttempts): ?\DateTime
    {
        foreach (self::DUNNING_SCHEDULE as $step) {
            if ($failedAttempts < $step['day']) {
                return now()->addDays($step['day'] - $failedAttempts);
            }
        }

        return null;
    }

    /**
     * Calculate remaining grace period days for a given plan.
     */
    public function remainingGraceDays(Tenant $tenant): int
    {
        $graceDays = $this->resolveGraceDays($tenant);

        if (! $tenant->suspended_at) {
            return $graceDays;
        }

        $elapsed = now()->diffInDays($tenant->suspended_at);

        return max(0, $graceDays - (int) $elapsed);
    }

    private function executeStep(Tenant $tenant, array $step, int $attempts): string
    {
        return match ($step['action']) {
            'notify' => $this->notify($tenant, $attempts),
            'notify_warning' => $this->notifyWarning($tenant, $attempts),
            'suspend' => $this->suspend($tenant),
            default => 'no_action',
        };
    }

    private function notify(Tenant $tenant, int $attempts): string
    {
        try {
            $tenant->notify(new PaymentFailedNotification($tenant, $attempts));
            Log::info('Dunning notification sent', [
                'tenant' => $tenant->id,
                'attempt' => $attempts,
            ]);
        } catch (\Throwable $e) {
            Log::error('Dunning notification failed', [
                'tenant' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }

        return 'notify';
    }

    private function notifyWarning(Tenant $tenant, int $attempts): string
    {
        $this->notify($tenant, $attempts);

        activity('billing')
            ->performedOn($tenant)
            ->withProperties(['attempts' => $attempts])
            ->log('dunning_warning_sent');

        return 'notify_warning';
    }

    private function suspend(Tenant $tenant): string
    {
        $tenant->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        try {
            $tenant->notify(new TenantSuspendedNotification($tenant));
        } catch (\Throwable $e) {
            Log::error('Suspension notification failed', [
                'tenant' => $tenant->id,
            ]);
        }

        activity('billing')
            ->performedOn($tenant)
            ->log('tenant_suspended_by_dunning');

        Log::alert('Tenant suspended by dunning', [
            'tenant' => $tenant->id,
            'suspended_at' => now(),
        ]);

        event(new TenantSuspendedByDunning(
            tenantId: $tenant->id,
            invoiceId: '',
        ));

        return 'suspended';
    }

    private function resolveGraceDays(Tenant $tenant): int
    {
        try {
            $plan = $tenant->plan;

            return (int) ($plan->features['grace_days'] ?? self::GRACE_PERIOD_DAYS);
        } catch (\Throwable) {
            return self::GRACE_PERIOD_DAYS;
        }
    }
}
