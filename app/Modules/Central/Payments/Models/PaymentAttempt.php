<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks individual checkout attempts for a payment.
 * A payment may have multiple attempts (user retries, retries after decline).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $payment_id
 * @property string $slug
 * @property string $status
 * @property array $payload Original PaymentData snapshot
 */
class PaymentAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'slug',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
