<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'subscription_id',
        'external_id',
        'number',
        'status',
        'amount_due',
        'amount_paid',
        'currency',
        'period_start',
        'period_end',
        'pdf_url',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        // Use Cashier's subscription model or our custom one if we extend it
        return $this->belongsTo(\Laravel\Cashier\Subscription::class);
    }
}
