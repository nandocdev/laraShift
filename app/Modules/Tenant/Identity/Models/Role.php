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

    protected static function booted(): void
    {
        static::deleting(function (Role $role) {
            if ($role->is_system) {
                throw new \Exception(__('System roles cannot be deleted.'));
            }
        });

        static::updating(function (Role $role) {
            if ($role->is_system && $role->isDirty('name')) {
                throw new \Exception(__('System roles cannot be renamed.'));
            }
        });
    }
}
