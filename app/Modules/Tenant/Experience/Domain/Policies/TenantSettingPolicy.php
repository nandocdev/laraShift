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
        if ((string) $user->tenant_id !== (string) $settings->tenant_id) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'Administrator', 'owner', 'Owner'])) {
            return true;
        }

        try {
            return $user->hasAnyPermission(['settings:manage', 'manage settings']);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
