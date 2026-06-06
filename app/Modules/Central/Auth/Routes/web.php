<?php

use App\Modules\Central\Auth\Livewire\Login;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/central', fn () => redirect()->route('central.dashboard'));
    Route::get('/central/login', Login::class)->name('central.login');
    Route::get('/central/login/challenge', \App\Modules\Central\Auth\Livewire\LoginChallenge::class)->name('central.login.challenge');
    Route::get('/central/forgot-password', \App\Modules\Central\Auth\Livewire\ForgotPassword::class)->name('central.password.request');
    Route::get('/central/reset-password/{token}', \App\Modules\Central\Auth\Livewire\ResetPassword::class)->name('central.password.reset');

    Route::middleware(['auth:central', \App\Modules\Central\Auth\Http\Middleware\ValidateCentralSession::class])->group(function () {
        Route::get('/central/dashboard', \App\Modules\Central\Auth\Livewire\Dashboard::class)->name('central.dashboard');
        Route::get('/central/settings/2fa', \App\Modules\Central\Auth\Livewire\TwoFactorEnrollment::class)->name('central.auth.2fa');

        Route::post('/central/logout', function (\App\Modules\Central\Auth\Actions\LogoutCentralUserAction $action) {
            $action->execute();
            return redirect('/');
        })->name('central.logout');
    });
});
