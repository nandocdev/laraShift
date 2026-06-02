<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
}
