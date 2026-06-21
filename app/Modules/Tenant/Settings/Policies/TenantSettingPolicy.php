<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Policies;

use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantSettingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the tenant settings.
     */
    public function update(User $user, TenantSetting $settings): bool
    {
        // For now, allow any user with 'admin' role or 'manage settings' permission
        return $user->hasRole('admin') || $user->hasPermissionTo('manage settings');
    }
}
