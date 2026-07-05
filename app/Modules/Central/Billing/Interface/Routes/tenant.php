<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Central\Billing\Interface\Livewire\ManageBilling;
use App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod;

Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
Route::get('/billing/plans', \App\Modules\Central\Billing\Interface\Livewire\SelectPlan::class)->name('tenant.billing.plans');
Route::get('/billing/checkout/hosted/{tenant_uuid}/{plan_uuid}', \App\Modules\Central\Billing\Interface\Livewire\HostedCheckout::class)->name('tenant.billing.checkout.hosted');
Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update-payment');

Route::get('/billing/success', function () {
    return view('billing::pages.success');
})->name('tenant.billing.success');

Route::get('/billing/cancel', function () {
    return view('billing::pages.cancel');
})->name('tenant.billing.cancel');
