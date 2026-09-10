<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasUuids, ScopedToTenant;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'provider_invoice_id',
        'amount_cents',
        'currency',
        'status',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
