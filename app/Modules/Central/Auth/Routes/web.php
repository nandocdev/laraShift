<?php

use App\Modules\Central\Auth\Livewire\Login;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/central/login', Login::class)->name('central.login');
    Route::get('/central/forgot-password', \App\Modules\Central\Auth\Livewire\ForgotPassword::class)->name('central.password.request');
    Route::get('/central/reset-password/{token}', \App\Modules\Central\Auth\Livewire\ResetPassword::class)->name('central.password.reset');

    Route::middleware('auth:central')->group(function () {
        Route::get('/central/dashboard', function () {
            return "Bienvenido al Dashboard Central";
        })->name('central.dashboard');

        Route::post('/central/logout', function (\App\Modules\Central\Auth\Actions\LogoutCentralUserAction $action) {
            $action->execute();
            return redirect()->route('central.login');
        })->name('central.logout');
    });
});
