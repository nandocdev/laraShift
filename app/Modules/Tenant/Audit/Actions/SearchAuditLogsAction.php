<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Actions;

use App\Modules\Tenant\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

final readonly class SearchAuditLogsAction
{
    /**
     * Search and filter audit logs with pagination.
     *
     * @param array{actor?: string, action?: string, resource?: string, date_from?: string, date_to?: string, resource_id?: string} $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function execute(array $filters, int $perPage = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AuditLog::with('user')->latest();

        $query = $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @param array{actor?: string, action?: string, resource?: string, date_from?: string, date_to?: string, resource_id?: string} $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['actor'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['actor']}%")
                    ->orWhere('email', 'like', "%{$filters['actor']}%");
            });
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['resource'])) {
            $query->where('resource', $filters['resource']);
        }

        if (! empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
