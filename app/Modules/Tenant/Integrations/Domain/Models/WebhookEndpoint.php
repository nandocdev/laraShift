<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_webhook_endpoints';

    protected $fillable = [
        'id',
        'tenant_id',
        'url',
        'events',
        'secret',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
