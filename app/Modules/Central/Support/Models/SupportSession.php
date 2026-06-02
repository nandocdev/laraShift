<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'operator_id',
        'reason',
        'token',
        'started_at',
        'ended_at',
        'expires_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'operator_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return is_null($this->ended_at) && $this->expires_at->isFuture();
    }
}
