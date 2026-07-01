<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\DTOs\SupportAuditEntryData;
use App\Modules\Shared\Contracts\SupportAuditVisibility;
use App\Modules\Tenant\Audit\Enums\AuditAction;
use App\Modules\Tenant\Audit\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final readonly class QueryTenantAuditLogsAction implements SupportAuditVisibility
{
    /**
     * @param array{
     *     date_from?: string,
     *     date_to?: string,
     *     action?: string,
     *     limit?: int,
     * } $filters
     * @return array<int, SupportAuditEntryData>
     */
    public function query(Tenant $tenant, array $filters = []): array
    {
        $visibleActions = AuditAction::visibleForSupport();
        $visibleValues = array_map(fn (AuditAction $a) => $a->value, $visibleActions);

        tenancy()->initialize($tenant);

        try {
            $query = AuditLog::whereIn('action', $visibleValues)
                ->select(['id', 'action', 'user_id', 'resource', 'resource_id', 'created_at'])
                ->latest();

            $dateFrom = $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d');
            $dateTo = $filters['date_to'] ?? now()->format('Y-m-d');

            $diff = Carbon::parse($dateFrom)->diffInDays($dateTo);

            if ($diff > 90) {
                Log::warning('Support audit query exceeded max range', [
                    'tenant_id' => $tenant->id,
                    'operator_id' => auth('central')->id(),
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]);

                $dateFrom = now()->subDays(90)->format('Y-m-d');
            }

            $query->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo);

            if (! empty($filters['action'])) {
                $query->where('action', $filters['action']);
            }

            $limit = min($filters['limit'] ?? 100, 500);

            $logs = $query->take($limit)->get();

            return $logs->map(function (AuditLog $log): SupportAuditEntryData {
                $actionValue = $log->action instanceof AuditAction ? $log->action->value : (string) $log->action;
                $severity = AuditAction::tryFrom($actionValue)?->severity() ?? 'UNKNOWN';

                return new SupportAuditEntryData(
                    id: $log->id,
                    action: $actionValue,
                    severity: $severity,
                    userName: $log->user?->name ?? 'System',
                    resource: $log->resource,
                    resourceId: $log->resource_id,
                    occurredAt: $log->created_at->toIso8601String(),
                );
            })->all();
        } finally {
            tenancy()->end();
        }
    }
}
