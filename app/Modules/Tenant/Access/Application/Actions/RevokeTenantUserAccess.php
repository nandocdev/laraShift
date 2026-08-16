<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Platform\Events\TenantUserRevoked;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class RevokeTenantUserAccess
{
    /**
     * Revokes access for a specific tenant user.
     *
     * @throws ValidationException
     */
    public function execute(User $targetUser, User $actor): void
    {
        // 1. Defend against self-revocation
        if ($targetUser->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot revoke your own access.'),
            ]);
        }

        // 2. Validate tenant consistency
        if ($targetUser->tenant_id !== tenant('id')) {
            throw new \InvalidArgumentException('Target user does not belong to the active tenant.');
        }

        // 3. Soft delete and mark inactive
        $targetUser->update(['status' => 'inactive']);
        $targetUser->delete();

        // 4. Audit log
        activity('identity')
            ->performedOn($targetUser)
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => tenant('id'),
                'revoked_user_email' => $targetUser->email,
            ])
            ->log('user_access_revoked');

        // 5. Dispatch domain event
        event(new TenantUserRevoked((string) $targetUser->id, (string) $targetUser->tenant_id, (string) $actor->id));
    }
}
