<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'locations';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'slug',
        'address',
        'phone',
        'email',
        'timezone',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'address' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];
}
