<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class RestoreTenantUserAccess
{
    /**
     * Reactivates a previously revoked (soft-deleted) member.
     *
     * @throws ValidationException
     */
    public function execute(User $targetUser, User $actor): void
    {
        if (! $targetUser->trashed()) {
            throw ValidationException::withMessages([
                'user' => __('This user is already active.'),
            ]);
        }

        if ($targetUser->tenant_id !== tenant('id')) {
            throw new \InvalidArgumentException('Target user does not belong to the active tenant.');
        }

        $targetUser->restore();
        $targetUser->update(['status' => 'active']);

        activity('identity')
            ->performedOn($targetUser)
            ->causedBy($actor)
            ->withProperties(['tenant_id' => tenant('id')])
            ->log('user_access_restored');
    }
}
