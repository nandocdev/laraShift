<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Interface\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Interface\Livewire\ManageBilling;
use App\Modules\Central\Billing\Interface\Livewire\SelectPlan;
use App\Modules\Central\Billing\Interface\Livewire\TenantInvoiceList;
use App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod;
use Illuminate\Support\Facades\Route;

Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
Route::get('/billing/plans', SelectPlan::class)->name('tenant.billing.plans');
Route::get('/billing/checkout/hosted/{plan}', HostedCheckout::class)->name('tenant.billing.checkout.hosted');
Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update_payment');
Route::get('/billing/invoices', TenantInvoiceList::class)->name('tenant.billing.invoices');
Route::view('/billing/success', 'billing::pages.success')->name('tenant.billing.success');
Route::view('/billing/cancel', 'billing::pages.cancel')->name('tenant.billing.cancel');
