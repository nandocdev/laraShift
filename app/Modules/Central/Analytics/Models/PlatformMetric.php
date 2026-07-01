<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformMetric extends Model
{
    use HasUuids;

    protected $table = 'platform_metrics';

    protected $fillable = [
        'id',
        'metric',
        'value',
        'period',
        'group',
        'captured_at',
    ];

    protected $casts = [
        'value' => 'float',
        'captured_at' => 'datetime',
    ];
}
