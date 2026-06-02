<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMfa extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'user_mfa';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'method',
        'secret',
        'recovery_codes',
        'enrolled_at',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'recovery_codes' => 'encrypted:json',
        'enrolled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
