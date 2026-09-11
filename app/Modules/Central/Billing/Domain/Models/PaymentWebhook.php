<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    use HasFactory, HasUuids, ScopedToTenant;

    protected $fillable = [
        'tenant_id',
        'gateway',
        'gateway_reference',
        'display_id',
        'status',
        'amount_cents',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
