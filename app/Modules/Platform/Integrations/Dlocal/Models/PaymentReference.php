<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentReference extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'external_reference',
        'order_id',
        'context',
        'tenant_id',
        'owner_type',
        'owner_id',
    ];

    protected $casts = [
        'id' => 'string',
    ];
}
