<?php

declare(strict_types=1);

return [
    /*
     * A tenant stuck in 'provisioning' longer than this is considered stale
     * and gets its ProvisionTenantJob re-dispatched by provisioning:reconcile.
     */
    'stale_provisioning_minutes' => (int) env('PROVISIONING_STALE_MINUTES', 30),
];
