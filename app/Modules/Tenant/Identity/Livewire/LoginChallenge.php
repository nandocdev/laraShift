<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Tenant\Identity\Models\User;
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
            'code' => 'required|string|size:6',
        ]);

        $userId = Session::get('login.id');

        // Use model with tenant scope
        $user = User::findOrFail($userId);

        $secret = $user->mfa->secret;

        if ($google2fa->verifyKey($secret, $this->code)) {
            Auth::guard('web')->login($user, Session::get('login.remember', false));

            Session::forget(['login.id', 'login.remember']);
            session()->regenerate();

            activity('identity')
                ->performedOn($user)
                ->log('tenant_user_logged_in_mfa');

            $this->redirectIntended(default: route('dashboard'));
        } else {
            $this->addError('code', __('Invalid verification code.'));
        }
    }

    public function render(): View
    {
        return view('identity::livewire.login-challenge');
    }
}
