<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Auth\Actions\LoginCentralUserAction;
use App\Modules\Central\Auth\Models\CentralUser;
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
            $this->redirect(route('central.login'), navigate: true);
        }
    }

    public function verify(Google2FA $google2fa): void
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = Session::get('login.id');
        $user = CentralUser::findOrFail($userId);

        $secret = $user->twoFactorAuth->secret;

        if ($google2fa->verifyKey($secret, $this->code)) {
            $action = app(LoginCentralUserAction::class);

            DB::transaction(function () use ($user, $action) {
                Auth::guard('central')->login($user, Session::get('login.remember', false));
                Session::forget(['login.id', 'login.remember']);
                session()->regenerate();
                $action->recordSession($user);
            });

            activity('auth')
                ->performedOn($user)
                ->log('central_user_logged_in_mfa');

            $this->redirectIntended(default: route('central.dashboard'));
        } else {
            $this->addError('code', __('Invalid verification code.'));
        }
    }

    public function render(): View
    {
        return view('central-auth::pages.challenge');
    }
}
