<?php

declare(strict_types=1);

use App\Modules\Platform\Integrations\Dlocal\Interface\Http\Controllers\DlocalWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/dlocal', [DlocalWebhookController::class, 'handle'])
    ->name('payments.webhooks.dlocal')
    ->withoutMiddleware(['web', 'auth', 'tenant']);
