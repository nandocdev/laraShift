<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Domain\Policies;

use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class TenantSettingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the tenant settings.
     */
    public function update(User $user, TenantSetting $settings): bool
    {
        // For now, allow any user with 'admin' role or 'manage settings' permission
        if ($user->hasRole('admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo('manage settings');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
