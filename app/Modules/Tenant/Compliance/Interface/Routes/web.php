<?php

declare(strict_types=1);

use App\Modules\Tenant\Compliance\Infrastructure\Http\Controllers\AuditDownloadController;
use App\Modules\Tenant\Compliance\Interface\Livewire\AuditLogViewer;
use App\Modules\Tenant\Compliance\Interface\Livewire\DataExport;
use Illuminate\Support\Facades\Route;

Route::get('/audit', AuditLogViewer::class)->name('tenant.audit.index');
Route::get('/settings/export', DataExport::class)->name('tenant.settings.export');
Route::get('/audit/download', AuditDownloadController::class)->name('tenant.audit.download');
Route::get('/data/download', AuditDownloadController::class)->name('tenant.data.download');
