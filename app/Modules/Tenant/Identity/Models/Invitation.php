<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_invitations';

    protected $fillable = [
        'id',
        'tenant_id',
        'email',
        'role_id',
        'token_hash',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
