<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\DTOs\SupportAuditEntryData;

interface SupportAuditVisibility
{
    /**
     * Query tenant audit logs from a support (CENTRAL) context.
     *
     * Requirements:
     * - Must initialize tenant context before querying.
     * - Must only return events visible to support agents.
     * - Must not expose PII beyond what the contract defines.
     * - Must limit date range to prevent abuse.
     *
     * @param array{
     *     date_from?: string,
     *     date_to?: string,
     *     action?: string,
     *     limit?: int,
     * } $filters
     * @return array<int, SupportAuditEntryData>
     */
    public function query(Tenant $tenant, array $filters = []): array;
}
