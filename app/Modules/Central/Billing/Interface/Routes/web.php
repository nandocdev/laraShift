<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Interface\Livewire\SubscriptionList;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/billing/subscriptions', SubscriptionList::class)->name('central.billing.subscriptions');
});
