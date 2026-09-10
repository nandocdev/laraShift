<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Central table: no tenant_id, no tenant scope. The tenant is resolved
 * from the event itself (provider_customer_id → payment_references).
 */
class PaymentGatewayEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'gateway',
        'gateway_event_id',
        'event_type',
        'payload',
        'processed_at',
        'failed_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
