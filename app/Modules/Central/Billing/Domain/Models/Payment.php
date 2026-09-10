<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Central\Billing\Database\Factories\PaymentFactory;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUuids, ScopedToTenant;

    protected $fillable = [
        'tenant_id',
        'slug',
        'display_id',
        'amount_cents',
        'currency',
        'status',
        'gateway',
        'gateway_reference',
        'subscription_id',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'provider_metadata' => 'array',
        ];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
