<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'provider_plan_id',
        'price_monthly',
        'price_yearly',
        'currency',
        'interval',
        'features',
        'is_active',
        'is_custom',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function gatewayIds(): array
    {
        $ids = $this->features['gateway_ids'] ?? [];

        return is_array($ids) ? $ids : [];
    }
}
