<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_notification_templates';

    protected $fillable = [
        'id',
        'tenant_id',
        'key',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
