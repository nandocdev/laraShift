<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'step',
        'status',
        'error',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
