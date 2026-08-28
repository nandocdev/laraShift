<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Data\Casts\MoneyCast;
use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasUuids, ScopedToTenant;

    protected $fillable = [
        'id',
        'tenant_id',
        'subscription_id',
        'provider_invoice_id',
        'amount',
        'currency',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'amount' => MoneyCast::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
