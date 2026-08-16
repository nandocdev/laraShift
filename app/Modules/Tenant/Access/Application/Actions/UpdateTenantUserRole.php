<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\Role;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final readonly class UpdateTenantUserRole
{
    /**
     * Updates the role for a specific tenant member.
     *
     * @throws ValidationException
     */
    public function execute(User $targetUser, string $roleName, User $actor): void
    {
        // 1. Defend against self-modification
        if ($targetUser->id === $actor->id) {
            throw ValidationException::withMessages([
                'newRole' => __('You cannot change your own role.'),
            ]);
        }

        // 2. Validate tenant consistency
        if ($targetUser->tenant_id !== tenant('id')) {
            throw new \InvalidArgumentException('Target user does not belong to the active tenant.');
        }

        // 3. Resolve role within the active tenant
        $role = Role::where('name', $roleName)->firstOrFail();

        // 4. Set Spatie permissions team context and sync
        setPermissionsTeamId(tenant('id'));
        $targetUser->syncRoles([$role->name]);

        // 5. Invalidate cached permissions for immediate effect (< 5s SLA)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 6. Log immutable audit activity
        activity('identity')
            ->performedOn($targetUser)
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => tenant('id'),
                'new_role' => $role->name,
                'previous_roles' => $targetUser->getRoleNames()->toArray(),
            ])
            ->log('user_role_changed');
    }
}
