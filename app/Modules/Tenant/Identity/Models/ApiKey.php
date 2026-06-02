<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_api_keys';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'key_hash',
        'scopes',
        'created_by',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at);
    }
}
