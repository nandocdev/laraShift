<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Actions;

use App\Modules\Tenant\Audit\DTOs\AuditLogData;
use App\Modules\Tenant\Audit\Models\AuditLog;

final readonly class RecordAuditLogAction
{
    /**
     * Records an audit log entry within the current tenant context.
     * Strategy: Rely on BelongsToTenant and HasUuids traits.
     */
    public function execute(AuditLogData $data): AuditLog
    {
        return AuditLog::create([
            'user_id' => $data->userId ?? auth()->id(),
            'action' => is_string($data->action) ? $data->action : $data->action->value,
            'resource' => $data->resource,
            'resource_id' => $data->resourceId,
            'metadata' => $data->metadata,
            'ip' => $data->ip ?? request()->ip(),
        ]);
    }
}
