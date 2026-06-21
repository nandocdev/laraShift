<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Identity\Models\UserMfa;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final readonly class EnrollTenant2FAAction
{
    public function __construct(
        private Google2FA $google2fa
    ) {}

    /**
     * Initiates the 2FA enrollment process for a tenant user.
     */
    public function initiate(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name') . ' (Tenant)',
            $user->email,
            $secret
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    /**
     * Confirms and saves the 2FA enrollment for a tenant user.
     */
    public function confirm(User $user, string $secret, string $code): bool
    {
        if (! $this->google2fa->verifyKey($secret, $code)) {
            return false;
        }

        UserMfa::updateOrCreate(
            ['user_id' => $user->id, 'tenant_id' => tenant('id')],
            [
                'id' => Str::uuid()->toString(),
                'method' => 'totp',
                'secret' => $secret,
                'recovery_codes' => $this->generateRecoveryCodes(),
                'enrolled_at' => now(),
            ]
        );

        $user->update(['mfa_enabled' => true]);

        activity('identity')
            ->performedOn($user)
            ->log('tenant_user_2fa_enrolled');

        return true;
    }

    private function generateRecoveryCodes(): array
    {
        return Collection::times(8, function () {
            return Str::random(10) . '-' . Str::random(10);
        })->toArray();
    }
}
