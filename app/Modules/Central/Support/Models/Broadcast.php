<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    use HasUuids;

    protected $connection = 'central';

    protected $fillable = [
        'id',
        'created_by',
        'title',
        'body',
        'filter_type',
        'filter_value',
        'channels',
        'sent_at',
        'recipient_count',
    ];

    protected $casts = [
        'channels' => 'array',
        'sent_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }
}
