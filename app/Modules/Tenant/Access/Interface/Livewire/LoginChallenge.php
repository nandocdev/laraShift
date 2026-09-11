<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Livewire;

use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Layout('layouts.auth')]
class LoginChallenge extends Component
{
    public string $code = '';

    public function mount(): void
    {
        if (! Session::has('login.id')) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function verify(Google2FA $google2fa): void
    {
        $this->validate([
            'code' => 'required|string|max:25',
        ]);

        $userId = Session::get('login.id');

        // Use model with tenant scope
        $user = User::findOrFail($userId);

        $secret = $user->mfa?->secret;

        if (is_string($secret) && $secret !== '' && $google2fa->verifyKey($secret, $this->code)) {
            $this->loginUser($user);

            activity('identity')
                ->performedOn($user)
                ->log('tenant_user_logged_in_mfa');

            $this->redirectIntended(default: route('dashboard'));

            return;
        }

        if ($this->consumeRecoveryCode($user, $this->code)) {
            $this->loginUser($user);

            activity('identity')
                ->performedOn($user)
                ->log('tenant_user_logged_in_recovery_code');

            $this->redirectIntended(default: route('dashboard'));

            return;
        }

        $this->addError('code', __('Invalid verification code.'));
    }

    private function loginUser(User $user): void
    {
        Auth::guard('web')->login($user, Session::get('login.remember', false));

        Session::forget(['login.id', 'login.remember']);
        session()->regenerate();
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->mfa?->recovery_codes;

        if (! is_array($codes) || $codes === []) {
            return false;
        }

        foreach ($codes as $index => $stored) {
            if (is_string($stored) && hash_equals($stored, $code)) {
                unset($codes[$index]);

                $user->mfa->update(['recovery_codes' => array_values($codes)]);

                return true;
            }
        }

        return false;
    }

    public function render(): View
    {
        return view('identity::livewire.login-challenge');
    }
}
