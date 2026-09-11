<?php

declare(strict_types=1);

use App\Modules\Tenant\Workspace\Interface\Livewire\CloseWorkspace;
use App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', fn () => view('workspace::pages.dashboard'))->name('dashboard');
Route::get('/team/members', TeamManagement::class)->name('tenant.team.index')->middleware('can:team:manage');
Route::get('/settings/workspace/danger-zone', CloseWorkspace::class)->name('tenant.workspace.danger-zone');
