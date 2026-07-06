<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_notifications';
}
