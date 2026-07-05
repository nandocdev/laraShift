<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/auth/login', \App\Modules\Tenant\Access\Interface\Livewire\Login::class)->name('login');
Route::post('/auth/login', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'store'])->name('login.store');
Route::post('/auth/logout', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::get('/auth/2fa/verify', \App\Modules\Tenant\Access\Interface\Livewire\LoginChallenge::class)->name('two-factor.login');
Route::post('/auth/2fa/verify', [\Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController::class, 'store'])->name('two-factor.login.store');

Route::post('/auth/forgot-password', [\Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'store'])->name('password.email');
Route::get('/auth/forgot-password', fn () => view('pages::auth.forgot-password'))->name('password.request');
Route::post('/auth/reset-password', [\Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'store'])->name('password.update');
Route::get('/auth/reset-password/{token}', fn ($token) => view('pages::auth.reset-password', ['token' => $token]))->name('password.reset');

Route::get('/auth/register', fn () => view('pages::auth.register'))->name('register');
Route::post('/auth/register', [\Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'store'])->name('register.store');

Route::get('/auth/verify-email', fn () => view('pages::auth.verify-email'))->name('verification.notice');
Route::get('/auth/verify-email/{id}/{hash}', [\Laravel\Fortify\Http\Controllers\VerifyEmailController::class, '__invoke'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/auth/email/verification-notification', [\Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController::class, 'store'])->middleware(['throttle:6,1'])->name('verification.send');

Route::get('/auth/confirm-password', fn () => view('pages::auth.confirm-password'))->name('password.confirm');
Route::post('/auth/confirm-password', [\Laravel\Fortify\Http\Controllers\ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');

Route::post('/auth/passkeys/login', [\Laravel\Passkeys\Http\Controllers\PasskeyLoginController::class, 'store'])->name('passkey.login');
Route::get('/auth/passkeys/login/options', [\Laravel\Passkeys\Http\Controllers\PasskeyLoginController::class, 'index'])->name('passkey.login-options');
Route::post('/auth/passkeys/register', [\Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController::class, 'store'])->name('passkey.register');
Route::get('/auth/passkeys/register/options', [\Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController::class, 'index'])->name('passkey.register-options');
Route::post('/auth/passkeys/confirm', [\Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController::class, 'store'])->name('passkey.confirm');
Route::get('/auth/passkeys/confirm/options', [\Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController::class, 'index'])->name('passkey.confirm-options');

Route::get('/auth/invitations/{token}/accept', \App\Modules\Tenant\Access\Interface\Livewire\AcceptInvitation::class)->name('tenant.invitations.accept');
