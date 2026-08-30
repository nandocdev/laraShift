<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Queries;

use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetAuditLogsForExport
{
    /**
     * Ejecuta la consulta de logs de auditoría para exportación optimizada para índices PostgreSQL.
     *
     * @return Builder<AuditLog>
     */
    public function execute(
        string $dateFrom,
        string $dateTo
    ): Builder {
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();

        return AuditLog::query()
            ->select(['id', 'tenant_id', 'created_at', 'action', 'user_id', 'resource', 'resource_id', 'ip', 'metadata'])
            ->with(['user:id,name'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->oldest('created_at');
    }
}
