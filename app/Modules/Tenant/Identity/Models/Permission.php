<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
