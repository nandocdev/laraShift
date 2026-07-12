<?php

declare(strict_types=1);

use App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings;
use App\Modules\Tenant\Experience\Interface\Livewire\LandingBuilder;
use App\Modules\Tenant\Experience\Interface\Livewire\LocalizationSettings;
use Illuminate\Support\Facades\Route;

Route::get('/settings/branding', BrandingSettings::class)->name('tenant.settings.branding');
Route::get('/settings/localization', LocalizationSettings::class)->name('tenant.settings.localization');
Route::get('/landings/{landing}/builder', LandingBuilder::class)->name('tenant.landings.builder');
