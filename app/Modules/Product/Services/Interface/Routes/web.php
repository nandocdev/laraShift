<?php

declare(strict_types=1);

use App\Modules\Product\Services\Interface\Livewire\ManageServices;
use Illuminate\Support\Facades\Route;

Route::get('/services', ManageServices::class)->name('tenant.services.index')->middleware('can:services:manage');
