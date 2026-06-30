<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Services;

use App\Modules\Central\Billing\Models\Plan;

final readonly class ProrationCalculator
{
    private const int SECONDS_IN_MONTH = 2_592_000;

    /**
     * Calculate prorated credit for the remaining time on the old plan.
     */
    public function calculateCredit(Plan $oldPlan, Plan $newPlan, ?\DateTimeInterface $periodEnd = null): float
    {
        $periodEnd ??= now()->addMonth();

        $secondsRemaining = max(0, $periodEnd->getTimestamp() - now()->getTimestamp());
        $ratio = $secondsRemaining / self::SECONDS_IN_MONTH;

        $oldPrice = (float) ($oldPlan->price_monthly?->getAmount() ?? 0);

        return round($oldPrice * $ratio, 2);
    }

    /**
     * Calculate the prorated charge for the new plan.
     */
    public function calculateCharge(Plan $oldPlan, Plan $newPlan, ?\DateTimeInterface $periodEnd = null): float
    {
        $periodEnd ??= now()->addMonth();

        $secondsRemaining = max(0, $periodEnd->getTimestamp() - now()->getTimestamp());
        $ratio = $secondsRemaining / self::SECONDS_IN_MONTH;

        $newPrice = (float) ($newPlan->price_monthly?->getAmount() ?? 0);

        return round($newPrice * $ratio, 2);
    }

    /**
     * Calculate the net amount (charge - credit) for the upgrade/downgrade.
     * Positive = tenant owes money. Negative = tenant gets credit.
     */
    public function calculateNetAmount(Plan $oldPlan, Plan $newPlan, ?\DateTimeInterface $periodEnd = null): float
    {
        $credit = $this->calculateCredit($oldPlan, $newPlan, $periodEnd);
        $charge = $this->calculateCharge($oldPlan, $newPlan, $periodEnd);

        return round($charge - $credit, 2);
    }

    /**
     * Determine if this is an upgrade (more expensive) or downgrade.
     */
    public function isUpgrade(Plan $oldPlan, Plan $newPlan): bool
    {
        $oldPrice = (float) ($oldPlan->price_monthly?->getAmount() ?? 0);
        $newPrice = (float) ($newPlan->price_monthly?->getAmount() ?? 0);

        return $newPrice >= $oldPrice;
    }

    /**
     * Get a human-readable description of the proration.
     */
    public function describe(Plan $oldPlan, Plan $newPlan, ?\DateTimeInterface $periodEnd = null): array
    {
        $credit = $this->calculateCredit($oldPlan, $newPlan, $periodEnd);
        $charge = $this->calculateCharge($oldPlan, $newPlan, $periodEnd);
        $net = $charge - $credit;
        $type = $this->isUpgrade($oldPlan, $newPlan) ? 'upgrade' : 'downgrade';

        return [
            'type' => $type,
            'old_plan' => $oldPlan->name,
            'new_plan' => $newPlan->name,
            'credit' => $credit,
            'charge' => $charge,
            'net_amount' => $net,
            'immediate_charge' => $net > 0 ? $net : 0,
            'credit_to_apply' => $net < 0 ? abs($net) : 0,
        ];
    }
}
