<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use App\Modules\Product\Services\Database\Factories\ServiceCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'service_categories';

    protected static function newFactory(): ServiceCategoryFactory
    {
        return ServiceCategoryFactory::new();
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'slug',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
