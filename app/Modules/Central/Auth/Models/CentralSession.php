<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentralSession extends Model
{
    use HasUuids;

    protected $table = 'central_sessions';

    protected $fillable = [
        'id',
        'user_id',
        'session_id',
        'token_hash',
        'ip',
        'user_agent',
        'issued_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'user_id');
    }

    public function revoke(string $reason = 'Manual revocation'): void
    {
        $this->update(['revoked_at' => now()]);
        
        // Note: Ideally we should also remove the session from Laravel's storage
        // but it depends on the driver. If using DB driver, we can delete from 'sessions' table.
    }
}
