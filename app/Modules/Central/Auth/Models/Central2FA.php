<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Central2FA extends Model
{
    use HasUuids;

    protected $table = 'central_2fa';

    protected $fillable = [
        'id',
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
        return $this->belongsTo(CentralUser::class, 'user_id');
    }
}
