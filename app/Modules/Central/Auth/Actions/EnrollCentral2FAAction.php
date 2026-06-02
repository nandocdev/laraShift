<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Auth\Models\Central2FA;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final readonly class EnrollCentral2FAAction
{
    public function __construct(
        private Google2FA $google2fa
    ) {}

    /**
     * Initiates the 2FA enrollment process.
     * Returns the secret and QR code URL.
     */
    public function initiate(CentralUser $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    /**
     * Confirms and saves the 2FA enrollment.
     */
    public function confirm(CentralUser $user, string $secret, string $code): bool
    {
        if (! $this->google2fa->verifyKey($secret, $code)) {
            return false;
        }

        Central2FA::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id' => Str::uuid()->toString(),
                'method' => 'totp',
                'secret' => $secret,
                'recovery_codes' => $this->generateRecoveryCodes(),
                'enrolled_at' => now(),
            ]
        );

        activity('auth')
            ->performedOn($user)
            ->log('2fa_enrolled');

        return true;
    }

    private function generateRecoveryCodes(): array
    {
        return Collection::times(8, function () {
            return Str::random(10) . '-' . Str::random(10);
        })->toArray();
    }
}
