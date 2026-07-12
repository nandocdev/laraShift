<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Platform\Security\Mfa\MfaService;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Domain\Models\UserMfa;
use Illuminate\Support\Str;

final readonly class EnrollTenantMfa
{
    public function __construct(
        private MfaService $mfa,
    ) {}

    /**
     * Initiates the 2FA enrollment process for a tenant user.
     */
    public function initiate(User $user): array
    {
        return $this->mfa->generateSecret(
            $user->email,
            config('app.name').' (Tenant)',
        );
    }

    /**
     * Confirms and saves the 2FA enrollment for a tenant user.
     */
    public function confirm(User $user, string $secret, string $code): bool
    {
        if (! $this->mfa->verify($secret, $code)) {
            return false;
        }

        UserMfa::updateOrCreate(
            ['user_id' => $user->id, 'tenant_id' => tenant('id')],
            [
                'id' => Str::uuid()->toString(),
                'method' => 'totp',
                'secret' => $secret,
                'recovery_codes' => $this->mfa->generateRecoveryCodes(),
                'enrolled_at' => now(),
            ]
        );

        $user->update(['mfa_enabled' => true]);

        activity('identity')
            ->performedOn($user)
            ->log('tenant_user_2fa_enrolled');

        return true;
    }
}
