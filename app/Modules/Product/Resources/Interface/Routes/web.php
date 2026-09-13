<?php

declare(strict_types=1);

use App\Modules\Product\Resources\Interface\Livewire\ManageResources;
use Illuminate\Support\Facades\Route;

Route::get('/resources', ManageResources::class)->name('tenant.resources.index')->middleware('can:resources:manage');
