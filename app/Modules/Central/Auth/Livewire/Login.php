<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Auth\Actions\LoginCentralUserAction;
use App\Modules\Central\Auth\DTOs\LoginData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Procesa el intento de login para el área central.
     */
    public function authenticate(LoginCentralUserAction $action): void
    {
        $this->validate();

        $data = new LoginData(
            email: $this->email,
            password: $this->password,
            remember: $this->remember
        );

        $result = $action->execute($data);

        if ($result === 'success') {
            // Record tracking session with the NEW regenerated ID
            $action->completeLogin(auth('central')->user(), $data->remember);

            $this->redirectIntended(default: route('central.dashboard'));

            return;
        }

        if ($result === 'requires_2fa') {
            $this->redirect(route('central.login.challenge'), navigate: true);

            return;
        }

        $this->addError('email', __('auth.failed'));
    }

    public function render(): View
    {
        return view('central-auth::pages.login')
            ->title(__('Login Central'));
    }
}
