<?php

declare(strict_types=1);

use App\Modules\Tenant\Access\Interface\Livewire\AcceptInvitation;
use App\Modules\Tenant\Access\Interface\Livewire\Login;
use App\Modules\Tenant\Access\Interface\Livewire\LoginChallenge;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;

Route::get('/auth/login', Login::class)->name('login');
Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::get('/auth/2fa/verify', LoginChallenge::class)->name('two-factor.login');
Route::post('/auth/2fa/verify', [TwoFactorAuthenticatedSessionController::class, 'store'])->name('two-factor.login.store');

Route::post('/auth/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::get('/auth/forgot-password', fn () => view('pages::auth.forgot-password'))->name('password.request');
Route::post('/auth/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
Route::get('/auth/reset-password/{token}', fn ($token) => view('pages::auth.reset-password', ['token' => $token]))->name('password.reset');

Route::get('/auth/register', fn () => view('pages::auth.register'))->name('register');
Route::post('/auth/register', [RegisteredUserController::class, 'store'])->name('register.store');

Route::get('/auth/verify-email', fn () => view('pages::auth.verify-email'))->name('verification.notice');
Route::get('/auth/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/auth/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware(['throttle:6,1'])->name('verification.send');

Route::get('/auth/confirm-password', fn () => view('pages::auth.confirm-password'))->name('password.confirm');
Route::post('/auth/confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');

Route::post('/auth/passkeys/login', [PasskeyLoginController::class, 'store'])->name('passkey.login');
Route::get('/auth/passkeys/login/options', [PasskeyLoginController::class, 'index'])->name('passkey.login-options');
Route::post('/auth/passkeys/register', [PasskeyRegistrationController::class, 'store'])->name('passkey.register');
Route::get('/auth/passkeys/register/options', [PasskeyRegistrationController::class, 'index'])->name('passkey.register-options');
Route::post('/auth/passkeys/confirm', [PasskeyConfirmationController::class, 'store'])->name('passkey.confirm');
Route::get('/auth/passkeys/confirm/options', [PasskeyConfirmationController::class, 'index'])->name('passkey.confirm-options');

Route::get('/auth/invitations/{token}/accept', AcceptInvitation::class)->name('tenant.invitations.accept');
