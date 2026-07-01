<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Models;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantEncryptionKey extends Model
{
    use HasUuids;

    protected $table = 'tenant_encryption_keys';

    protected $fillable = [
        'id',
        'tenant_id',
        'key_identifier',
        'encrypted_key',
        'purpose',
        'is_active',
        'rotated_at',
        'expires_at',
        'rotated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rotated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
