<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Queries;

use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

final class GetAuditLogsForExport
{
    /**
     * Ejecuta la consulta de logs de auditoría para exportación.
     * Aplica filtros de fecha y ordenamiento, evitando N+1 mediante eager loading.
     *
     * @return Builder<AuditLog>
     */
    public function execute(
        string $tenantId,
        string $dateFrom,
        string $dateTo
    ): Builder {
        return AuditLog::with('user')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->oldest();
    }
}
