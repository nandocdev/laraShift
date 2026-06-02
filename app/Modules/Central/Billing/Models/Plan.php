<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'is_active',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
        'price_monthly' => 'integer',
        'price_yearly' => 'integer',
    ];
}
