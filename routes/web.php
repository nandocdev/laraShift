<?php

use Illuminate\Support\Facades\Route;

Route::get('/', \App\Modules\Central\Marketing\Livewire\LandingPage::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
