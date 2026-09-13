<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Services\Database\Factories\ServiceFactory;
use App\Modules\Product\Services\Domain\Enums\DepositType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'services';

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'capacity',
        'price_cents',
        'currency',
        'deposit_type',
        'deposit_value',
        'cancellation_window_hours',
        'cancellation_policy',
        'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'buffer_before_minutes' => 'integer',
        'buffer_after_minutes' => 'integer',
        'capacity' => 'integer',
        'price_cents' => 'integer',
        'deposit_type' => DepositType::class,
        'deposit_value' => 'integer',
        'cancellation_window_hours' => 'integer',
        'cancellation_policy' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Location, $this, LocationServicePivot, 'service'>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_service', 'service_id', 'location_id')
            ->using(LocationServicePivot::class)
            ->withPivot('tenant_id');
    }
}
