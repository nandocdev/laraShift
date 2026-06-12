<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use App\Modules\Central\Features\Models\Feature;
use App\Modules\Shared\Infrastructure\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'amount',
        'currency',
        'interval',
        'interval_count',
        'provider_plan_id',
        'is_active',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
        'price_monthly' => MoneyCast::class,
        'price_yearly' => MoneyCast::class,
        'amount' => MoneyCast::class,
    ];

    public function catalogFeatures(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features');
    }
}
