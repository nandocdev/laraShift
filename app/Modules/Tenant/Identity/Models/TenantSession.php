<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models;

use App\Modules\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantSession extends BaseModel
{
    protected $table = 'tenant_sessions';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'session_id',
        'ip',
        'user_agent',
        'refresh_token_hash',
        'issued_at',
        'last_activity_at',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revoke(?string $by = null, string $reason = 'Manual revocation'): void
    {
        $this->update([
            'revoked_at' => now(),
            'revoked_by' => $by,
            'revoke_reason' => $reason,
        ]);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
