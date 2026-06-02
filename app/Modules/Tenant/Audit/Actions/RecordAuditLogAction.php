<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Actions;

use App\Modules\Tenant\Audit\Models\AuditLog;
use Illuminate\Support\Str;

final readonly class RecordAuditLogAction
{
    /**
     * Records an audit log entry within the current tenant context.
     */
    public function execute(
        string $action,
        ?string $resource = null,
        ?string $resourceId = null,
        ?array $metadata = null
    ): AuditLog {
        return AuditLog::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'user_id' => auth()->id(),
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
            'ip' => request()->ip(),
        ]);
    }
}
