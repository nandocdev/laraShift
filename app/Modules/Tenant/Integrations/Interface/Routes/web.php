<?php

declare(strict_types=1);

use App\Modules\Tenant\Integrations\Interface\Livewire\ManageWebhooks;
use App\Modules\Tenant\Integrations\Interface\Livewire\SmtpSettings;
use Illuminate\Support\Facades\Route;

Route::get('/settings/smtp', SmtpSettings::class)->name('tenant.settings.smtp')->middleware('can:settings:manage');
Route::get('/settings/webhooks', ManageWebhooks::class)->name('tenant.settings.webhooks')->middleware('can:settings:manage');
