<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
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

    public function authenticate(): void
    {
        $this->validate();

        // The query is automatically scoped by tenant_id via BelongsToTenant trait
        $user = User::where('email', $this->email)->first();

        if (! $user || ! Hash::check($this->password, $user->password) || ! $user->is_active) {
            $this->addError('email', __('auth.failed'));
            return;
        }

        // Check if user has 2FA enabled
        if ($user->hasTwoFactorEnabled()) {
            Session::put([
                'login.id' => $user->id,
                'login.remember' => $this->remember,
            ]);

            $this->redirect(route('two-factor.login'), navigate: true);
            return;
        }
        
        Auth::guard('web')->login($user, $this->remember);

        Session::regenerate();

        activity('auth')
            ->performedOn($user)
            ->log('tenant_user_logged_in');

        $this->redirectIntended(default: route('dashboard'));
    }

    public function render(): View
    {
        return view('identity::livewire.login');
    }
}
