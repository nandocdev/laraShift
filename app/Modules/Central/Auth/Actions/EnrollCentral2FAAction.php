<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\Models\Central2FA;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Platform\Security\Mfa\MfaService;
use Illuminate\Support\Str;

final readonly class EnrollCentral2FAAction
{
    public function __construct(
        private MfaService $mfa,
    ) {}

    /**
     * Initiates the 2FA enrollment process.
     * Returns the secret and QR code URL.
     */
    public function initiate(CentralUser $user): array
    {
        return $this->mfa->generateSecret(
            $user->email,
            config('app.name'),
        );
    }

    /**
     * Confirms and saves the 2FA enrollment.
     */
    public function confirm(CentralUser $user, string $secret, string $code): bool
    {
        if (! $this->mfa->verify($secret, $code)) {
            return false;
        }

        Central2FA::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id' => Str::uuid()->toString(),
                'method' => 'totp',
                'secret' => $secret,
                'recovery_codes' => $this->mfa->generateRecoveryCodes(),
                'enrolled_at' => now(),
            ]
        );

        activity('auth')
            ->performedOn($user)
            ->log('2fa_enrolled');

        return true;
    }
}
