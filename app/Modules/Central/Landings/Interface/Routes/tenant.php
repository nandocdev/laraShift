<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', \App\Modules\Central\Landings\Http\Controllers\ServeTenantLandingController::class)->name('tenant.home');
Route::get('/landings/{landing}/builder', \App\Modules\Central\Landings\Livewire\LandingBuilder::class)->name('tenant.landings.builder');
