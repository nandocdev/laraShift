<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'gateway_event_id',
        'gateway',
        'event_type',
        'payload',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
