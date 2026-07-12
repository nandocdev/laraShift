<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class ResetPassword extends Component
{
    public string $token = '';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|min:12|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    /**
     * Restablece la contraseña del usuario.
     */
    public function resetPassword(): void
    {
        $this->validate();

        $status = Password::broker('central_users')->reset(
            [
                'token' => $this->token,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                activity('auth')
                    ->performedOn($user)
                    ->log('central_user_password_reset');
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', __($status));
            $this->redirect(route('central.login'));

            return;
        }

        $this->addError('email', __($status));
    }

    public function render()
    {
        return view('central-auth::pages.reset-password')
            ->title(__('Restablecer Contraseña Central'));
    }
}
