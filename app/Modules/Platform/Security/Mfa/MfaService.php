<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security\Mfa;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * Generates a TOTP secret and QR code URL for enrollment.
     */
    public function generateSecret(string $email, string $issuer): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            $issuer,
            $email,
            $secret,
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    /**
     * Verifies a TOTP code against a secret.
     */
    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Generates a set of recovery codes.
     *
     * @return array<string>
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, function () {
            return Str::random(10).'-'.Str::random(10);
        })->toArray();
    }
}
