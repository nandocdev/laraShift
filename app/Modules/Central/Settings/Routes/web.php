<?php

declare(strict_types=1);

use App\Modules\Central\Settings\Livewire\PlatformBranding;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/settings/branding', PlatformBranding::class)
        ->name('central.settings.branding');
});
