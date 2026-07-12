<?php

declare(strict_types=1);

use App\Modules\Tenant\Integrations\Interface\Livewire\SmtpSettings;
use Illuminate\Support\Facades\Route;

Route::get('/settings/smtp', SmtpSettings::class)->name('tenant.settings.smtp');
