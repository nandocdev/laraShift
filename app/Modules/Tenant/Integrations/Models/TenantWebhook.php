<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantWebhook extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_webhooks';

    protected $fillable = [
        'id',
        'tenant_id',
        'url',
        'secret',
        'events',
        'is_active',
        'max_retries',
        'timeout_seconds',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];
}
