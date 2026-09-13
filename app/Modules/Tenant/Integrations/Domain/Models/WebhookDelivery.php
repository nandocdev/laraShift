<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_webhook_deliveries';

    protected $fillable = [
        'id',
        'tenant_id',
        'endpoint_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'response_status',
        'error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }
}
