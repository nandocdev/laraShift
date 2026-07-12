<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Providers;

use App\Modules\Central\Auth\Livewire\Dashboard;
use App\Modules\Central\Auth\Livewire\ForgotPassword;
use App\Modules\Central\Auth\Livewire\Login;
use App\Modules\Central\Auth\Livewire\LoginChallenge;
use App\Modules\Central\Auth\Livewire\ResetPassword;
use App\Modules\Central\Auth\Livewire\TwoFactorEnrollment;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CentralAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'central-auth');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Livewire::component('central-auth-login', Login::class);
        Livewire::component('central-auth-login-challenge', LoginChallenge::class);
        Livewire::component('central-auth-forgot-password', ForgotPassword::class);
        Livewire::component('central-auth-reset-password', ResetPassword::class);
        Livewire::component('central-auth-2fa-enrollment', TwoFactorEnrollment::class);
        Livewire::component('central-auth-dashboard', Dashboard::class);
    }
}
