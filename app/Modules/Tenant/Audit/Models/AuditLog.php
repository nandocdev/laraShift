<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_audit_logs';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'action',
        'resource',
        'resource_id',
        'metadata',
        'ip',
    ];

    protected $casts = [
        'metadata' => 'array',
        'action' => \App\Modules\Tenant\Audit\Enums\AuditAction::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
