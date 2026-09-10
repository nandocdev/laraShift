<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReference extends Model
{
    use HasFactory, HasUuids, ScopedToTenant;

    public const CONTEXT_CUSTOMER = 'customer';

    public const CONTEXT_ORDER = 'order';

    protected $fillable = [
        'tenant_id',
        'external_reference',
        'order_id',
        'context',
        'owner_type',
        'owner_id',
    ];
}
