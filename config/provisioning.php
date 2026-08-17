<?php

declare(strict_types=1);

return [
    /*
     * A tenant stuck in 'provisioning' longer than this is considered stale
     * and gets its ProvisionTenantJob re-dispatched by provisioning:reconcile.
     */
    'stale_provisioning_minutes' => (int) env('PROVISIONING_STALE_MINUTES', 30),

    /*
     * A tenant in 'pending_payment' that has not paid within this window is
     * marked 'expired' by provisioning:reconcile.
     */
    'pending_payment_expiry_hours' => (int) env('PROVISIONING_PENDING_PAYMENT_EXPIRY_HOURS', 24),
];
