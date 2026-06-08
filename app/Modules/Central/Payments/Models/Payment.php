<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use App\Modules\Shared\Tenancy\Models\Concerns\TenantScope;

/**
 * @property string       $id
 * @property string       $tenant_id
 * @property string       $display_id       Your order/invoice ID
 * @property string       $slug             Unique slug sent to gateway
 * @property float        $amount
 * @property float        $tax_amount
 * @property float        $discount
 * @property string       $description
 * @property string       $email
 * @property string       $currency
 * @property string       $status           PaymentStatus value
 * @property string       $gateway
 * @property string|null  $gateway_reference
 * @property string|null  $authorization_code
 * @property string|null  $error_code
 */
class Payment extends Model {
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'display_id',
        'slug',
        'amount',
        'tax_amount',
        'discount',
        'description',
        'email',
        'currency',
        'status',
        'gateway',
        'gateway_reference',
        'authorization_code',
        'error_code',
    ];

    protected $casts = [
        'amount' => 'float',
        'tax_amount' => 'float',
        'discount' => 'float',
    ];

    // -------------------------------------------------------------------------
    // Tenant scope (complements RLS)
    // -------------------------------------------------------------------------

    protected static function booted(): void {
        static::addGlobalScope(new TenantScope());
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function attempts(): HasMany {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function webhooks(): HasMany {
        return $this->hasMany(PaymentWebhook::class, 'display_id', 'display_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function statusEnum(): PaymentStatus {
        return PaymentStatus::from($this->status);
    }

    public function isApproved(): bool {
        return $this->statusEnum() === PaymentStatus::Approved;
    }

    public function netAmount(): float {
        return round($this->amount - $this->discount + $this->tax_amount, 2);
    }
}
