<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\Invitation;
use App\Modules\Tenant\Access\Domain\Models\User;

final readonly class CancelTenantInvitation
{
    /**
     * Cancels/deletes an active tenant invitation.
     */
    public function execute(Invitation $invitation, User $actor): void
    {
        if ($invitation->tenant_id !== tenant('id')) {
            throw new \InvalidArgumentException('Invitation does not belong to the active tenant.');
        }

        $email = $invitation->email;
        $invitation->delete();

        activity('identity')
            ->performedOn($invitation)
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => tenant('id'),
                'email' => $email,
            ])
            ->log('invitation_cancelled');
    }
}
