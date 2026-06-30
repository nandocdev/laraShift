<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Models;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantHealthCheck extends Model
{
    use HasUuids;

    protected $table = 'tenant_health_checks';

    protected $fillable = [
        'id',
        'tenant_id',
        'check_type',
        'status',
        'message',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
