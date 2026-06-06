<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use App\Modules\Central\Features\Models\Feature;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'amount',
        'currency',
        'interval',
        'is_active',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
        'price_monthly' => 'integer',
        'price_yearly' => 'integer',
    ];

    public function catalogFeatures(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features');
    }
}
