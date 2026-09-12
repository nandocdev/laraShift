<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Retention (days)
    |--------------------------------------------------------------------------
    |
    | Tenant audit logs and central activity entries older than this are
    | deleted by compliance:prune-audit-logs (GDPR storage limitation).
    |
    */
    'audit_retention_days' => (int) env('COMPLIANCE_AUDIT_RETENTION_DAYS', 365),
];
