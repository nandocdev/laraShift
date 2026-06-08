<?php

declare(strict_types=1);

namespace App\Modules\Shared\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_notifications';
}
