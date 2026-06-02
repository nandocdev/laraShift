<?php

use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->get('/', \App\Modules\Central\Marketing\Livewire\LandingPage::class)->name('home.' . str_replace('.', '-', $domain));
}

// Catch-all home route for other central domains or fallbacks
Route::get('/', \App\Modules\Central\Marketing\Livewire\LandingPage::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
