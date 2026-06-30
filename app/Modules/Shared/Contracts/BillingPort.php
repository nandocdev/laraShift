<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface BillingPort
{
    public function createCheckoutSession(TenantContract $tenant, string $planId): string;

    public function cancelSubscription(TenantContract $tenant, string $subscriptionId, bool $immediately = false): void;

    public function getSubscriptionStatus(TenantContract $tenant): ?string;

    public function syncSubscription(TenantContract $tenant): void;

    public function hasActiveSubscription(TenantContract $tenant): bool;
}
