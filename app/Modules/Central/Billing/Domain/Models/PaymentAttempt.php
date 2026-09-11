<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    use HasFactory, HasUuids, ScopedToTenant;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'slug',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
