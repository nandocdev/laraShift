<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'notification_key',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
