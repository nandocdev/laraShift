<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantWebhookDelivery extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_webhook_deliveries';

    protected $fillable = [
        'id',
        'tenant_id',
        'webhook_id',
        'event',
        'payload',
        'attempt',
        'status',
        'response_status',
        'response_body',
        'next_retry_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(TenantWebhook::class, 'webhook_id');
    }
}
