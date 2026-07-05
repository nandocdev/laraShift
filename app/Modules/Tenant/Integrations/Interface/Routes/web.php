<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/settings/smtp', \App\Modules\Tenant\Integrations\Interface\Livewire\SmtpSettings::class)->name('tenant.settings.smtp');
