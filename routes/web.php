<?php

use App\Modules\Central\Marketing\Livewire\LandingPage;
use App\Modules\Central\Marketing\Livewire\RegisterTenant;
use App\Modules\Central\Marketing\Models\LegalDocument;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->group(function () use ($domain) {
        Route::get('/', LandingPage::class)->name('home.'.str_replace('.', '-', $domain));
        Route::get('/register', RegisterTenant::class)->name('register.'.str_replace('.', '-', $domain));
    });
}

// Catch-all home route for other central domains or fallbacks
Route::get('/', LandingPage::class)->name('home');
Route::get('/register', RegisterTenant::class)
    ->middleware(['throttle:5,1'])
    ->name('central.register');

Route::get('/terms', function () {
    $doc = \Illuminate\Support\Facades\Cache::remember('legal_doc:terms', 86400, function () {
        return LegalDocument::where('type', 'terms')
            ->where('is_published', true)->latest('version')->first();
    });

    return view('marketing::pages.public-legal', ['doc' => $doc]);
})->name('legal.terms');

Route::get('/privacy', function () {
    $doc = \Illuminate\Support\Facades\Cache::remember('legal_doc:privacy', 86400, function () {
        return LegalDocument::where('type', 'privacy')
            ->where('is_published', true)->latest('version')->first();
    });

    return view('marketing::pages.public-legal', ['doc' => $doc]);
})->name('legal.privacy');

require __DIR__.'/settings.php';
