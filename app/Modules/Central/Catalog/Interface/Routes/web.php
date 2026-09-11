<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Interface\Livewire\ManagePlans;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/catalog/plans', ManagePlans::class)->name('central.catalog.plans');
});
