<?php

declare(strict_types=1);

use App\Modules\Product\Locations\Interface\Livewire\ManageLocations;
use Illuminate\Support\Facades\Route;

Route::get('/locations', ManageLocations::class)->name('tenant.locations.index')->middleware('can:locations:manage');
