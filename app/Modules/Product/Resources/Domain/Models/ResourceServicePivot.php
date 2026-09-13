<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ResourceServicePivot extends Pivot
{
    use BelongsToTenant;

    protected $table = 'resource_service';

    public $incrementing = false;

    protected $fillable = [
        'resource_id',
        'service_id',
        'tenant_id',
    ];
}
