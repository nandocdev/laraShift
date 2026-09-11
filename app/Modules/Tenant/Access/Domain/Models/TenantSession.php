<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSession extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_sessions';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'session_id',
        'ip',
        'user_agent',
        'revoked_at',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at);
    }
}
