<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class ForgotPassword extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    /**
     * Envía el enlace de restablecimiento de contraseña.
     */
    public function sendResetLink(): void
    {
        $this->validate();

        $status = Password::broker('central_users')->sendResetLink(
            ['email' => $this->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
            $this->reset('email');
            return;
        }

        $this->addError('email', __($status));
    }

    public function render()
    {
        return view('central-auth::pages.forgot-password')
            ->title(__('Recuperar Contraseña Central'));
    }
}
