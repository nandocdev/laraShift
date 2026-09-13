<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Resources\Database\Factories\ResourceFactory;
use App\Modules\Product\Resources\Domain\Enums\ResourceType;
use App\Modules\Product\Services\Domain\Models\Service;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'resources';

    protected static function newFactory(): ResourceFactory
    {
        return ResourceFactory::new();
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'type',
        'name',
        'slug',
        'description',
        'capacity',
        'skills',
        'rate_cents',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'type' => ResourceType::class,
        'capacity' => 'integer',
        'skills' => 'array',
        'rate_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * @return BelongsToMany<Service, $this, ResourceServicePivot, 'resource'>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'resource_service', 'resource_id', 'service_id')
            ->using(ResourceServicePivot::class)
            ->withPivot('tenant_id');
    }
}
