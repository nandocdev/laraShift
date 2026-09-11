<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Platform\Events\TenantUserRevoked;
use App\Modules\Tenant\Access\Domain\Models\Role;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class DeleteTenantUserAction
{
    /**
     * Permanently deletes a workspace member. FK cascades remove sessions,
     * MFA and passkeys; invited_by references null out; audit rows keep
     * their user_id as forensic trail (no FK). Spatie pivots have no FK to
     * users, so roles/permissions are detached explicitly. Product modules
     * owning domain resources clean up through TenantUserRevoked.
     *
     * @throws ValidationException
     */
    public function execute(User $targetUser, User $actor): void
    {
        if ($targetUser->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        if ($targetUser->tenant_id !== tenant('id')) {
            throw new \InvalidArgumentException('Target user does not belong to the active tenant.');
        }

        if ($this->isLastAdmin($targetUser)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete the last administrator. Assign the role to someone else first.'),
            ]);
        }

        $email = $targetUser->email;
        $userId = (string) $targetUser->id;
        $tenantId = (string) $targetUser->tenant_id;

        $targetUser->roles()->detach();
        $targetUser->permissions()->detach();
        $targetUser->forceDelete();

        activity('identity')
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => tenant('id'),
                'deleted_user_email' => $email,
            ])
            ->log('user_deleted_permanently');

        event(new TenantUserRevoked($userId, $tenantId, (string) $actor->id));
    }

    private function isLastAdmin(User $targetUser): bool
    {
        if (! $targetUser->hasRole('admin')) {
            return false;
        }

        $adminRole = Role::where('name', 'admin')->first();

        return $adminRole
            && $adminRole->users()->count() <= 1;
    }
}
