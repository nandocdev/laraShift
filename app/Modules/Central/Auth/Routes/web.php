<?php

use App\Modules\Central\Auth\Http\Controllers\LogoutController;
use App\Modules\Central\Auth\Http\Middleware\ValidateCentralSession;
use App\Modules\Central\Auth\Livewire\Dashboard;
use App\Modules\Central\Auth\Livewire\ForgotPassword;
use App\Modules\Central\Auth\Livewire\ImpersonationLog;
use App\Modules\Central\Auth\Livewire\Login;
use App\Modules\Central\Auth\Livewire\LoginChallenge;
use App\Modules\Central\Auth\Livewire\ResetPassword;
use App\Modules\Central\Auth\Livewire\TwoFactorEnrollment;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/central', fn () => redirect()->route('central.dashboard'));
    Route::get('/central/login', Login::class)->name('central.login');
    Route::get('/central/login/challenge', LoginChallenge::class)->name('central.login.challenge');
    Route::get('/central/forgot-password', ForgotPassword::class)->name('central.password.request');
    Route::get('/central/reset-password/{token}', ResetPassword::class)->name('central.password.reset');

    Route::middleware(['auth:central', ValidateCentralSession::class])->group(function () {
        Route::get('/central/dashboard', Dashboard::class)->name('central.dashboard');
        Route::get('/central/settings/2fa', TwoFactorEnrollment::class)->name('central.auth.2fa');
        Route::get('/central/audit/impersonations', ImpersonationLog::class)->name('central.auth.impersonations');

        Route::post('/central/logout', LogoutController::class)->name('central.logout');
    });
});
