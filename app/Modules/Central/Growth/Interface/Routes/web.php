<?php

declare(strict_types=1);

use App\Modules\Central\Growth\Interface\Livewire\LandingPage;
use App\Modules\Central\Growth\Interface\Livewire\RegisterTenant;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->middleware('throttle:register')->group(function () use ($domain) {
        Route::get('/', LandingPage::class)->name('home.'.str_replace('.', '-', $domain));
        Route::get('/register', RegisterTenant::class)->name('register.'.str_replace('.', '-', $domain));
    });
}

// Catch-all home route for other central domains or fallbacks
Route::get('/', LandingPage::class)->name('home');
Route::get('/register', RegisterTenant::class)
    ->middleware(['throttle:register'])
    ->name('central.register');
