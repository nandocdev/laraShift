<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/settings/branding', \App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings::class)->name('tenant.settings.branding');
Route::get('/settings/localization', \App\Modules\Tenant\Experience\Interface\Livewire\LocalizationSettings::class)->name('tenant.settings.localization');
Route::get('/landings/{landing}/builder', \App\Modules\Tenant\Experience\Interface\Livewire\LandingBuilder::class)->name('tenant.landings.builder');
