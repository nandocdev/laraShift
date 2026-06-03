<?php

use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->group(function () use ($domain) {
        Route::get('/', \App\Modules\Central\Marketing\Livewire\LandingPage::class)->name('home.' . str_replace('.', '-', $domain));
        Route::get('/register', \App\Modules\Central\Marketing\Livewire\RegisterTenant::class)->name('register.' . str_replace('.', '-', $domain));
    });
}

// Catch-all home route for other central domains or fallbacks
Route::get('/', \App\Modules\Central\Marketing\Livewire\LandingPage::class)->name('home');
Route::get('/register', \App\Modules\Central\Marketing\Livewire\RegisterTenant::class)->name('register');

require __DIR__.'/settings.php';
