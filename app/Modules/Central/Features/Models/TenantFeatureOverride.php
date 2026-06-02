<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Models;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureOverride extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'feature_id',
        'type',
        'reason',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
