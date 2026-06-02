<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Modules\Central\Auth\Livewire\Login;

class CentralAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'central-auth');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        Livewire::component('central-auth-login', \App\Modules\Central\Auth\Livewire\Login::class);
        Livewire::component('central-auth-login-challenge', \App\Modules\Central\Auth\Livewire\LoginChallenge::class);
        Livewire::component('central-auth-forgot-password', \App\Modules\Central\Auth\Livewire\ForgotPassword::class);
        Livewire::component('central-auth-reset-password', \App\Modules\Central\Auth\Livewire\ResetPassword::class);
        Livewire::component('central-auth-2fa-enrollment', \App\Modules\Central\Auth\Livewire\TwoFactorEnrollment::class);
        Livewire::component('central-auth-dashboard', \App\Modules\Central\Auth\Livewire\Dashboard::class);
    }
}
