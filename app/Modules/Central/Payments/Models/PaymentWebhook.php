<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Shared\Tenancy\Models\Concerns\TenantScope;

/**
 * Immutable log of every inbound webhook from the gateway.
 * Never updated after insert. Idempotency key: (tenant_id, gateway_reference).
 *
 * @property string      $id
 * @property string      $tenant_id
 * @property string      $gateway_reference
 * @property string      $display_id
 * @property string      $status
 * @property float       $amount
 * @property string      $gateway_code
 * @property string|null $authorization_code
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string      $raw_payload         JSON string, preserved verbatim
 */
class PaymentWebhook extends Model {
    use HasUuids;

    // Webhooks are append-only: never allow mass update
    protected $guarded = ['updated_at'];

    protected $fillable = [
        'tenant_id',
        'gateway_reference',
        'display_id',
        'status',
        'amount',
        'gateway_code',
        'authorization_code',
        'error_code',
        'error_message',
        'raw_payload',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    protected static function booted(): void {
        static::addGlobalScope(new TenantScope());

        // Hard block: webhooks are immutable
        static::updating(fn() => false);
    }
}
