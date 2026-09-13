<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LocationServicePivot extends Pivot
{
    use BelongsToTenant;

    protected $table = 'location_service';

    public $incrementing = false;

    protected $fillable = [
        'location_id',
        'service_id',
        'tenant_id',
    ];
}
