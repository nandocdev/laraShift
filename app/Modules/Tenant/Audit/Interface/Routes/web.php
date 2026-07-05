<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/audit', \App\Modules\Tenant\Audit\Livewire\AuditLogViewer::class)->name('tenant.audit.index');
Route::get('/audit/download', \App\Modules\Tenant\Audit\Http\Controllers\AuditDownloadController::class)->name('tenant.audit.download');
Route::get('/data/download', \App\Modules\Tenant\Audit\Http\Controllers\AuditDownloadController::class)->name('tenant.data.download');
