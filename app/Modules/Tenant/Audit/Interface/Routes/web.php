<?php

declare(strict_types=1);

use App\Modules\Tenant\Audit\Http\Controllers\AuditDownloadController;
use App\Modules\Tenant\Audit\Interface\Livewire\DataExport;
use App\Modules\Tenant\Audit\Livewire\AuditLogViewer;
use Illuminate\Support\Facades\Route;

Route::get('/audit', AuditLogViewer::class)->name('tenant.audit.index');
Route::get('/settings/export', DataExport::class)->name('tenant.settings.export');
Route::get('/audit/download', AuditDownloadController::class)->name('tenant.audit.download');
Route::get('/data/download', AuditDownloadController::class)->name('tenant.data.download');
