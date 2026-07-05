<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Interface\Http\Controllers\BillingApiController;
use App\Modules\Central\Billing\Interface\Http\Controllers\DlocalWebhookController;
use App\Modules\Central\Billing\Interface\Http\Controllers\StripeWebhookController;
use App\Modules\Central\Billing\Interface\Livewire\SubscriptionList;
use App\Modules\Central\Billing\Interface\Livewire\TenantInvoiceList;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Application\Actions\GenerateInvoicePdf;
use Illuminate\Support\Facades\Route;

// Webhooks (Public with internal validation)
Route::post('/central/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])->name('central.billing.webhook.stripe');

// PagueloFacil Public Callback (Browser Redirect)
Route::get('/central/billing/paguelofacil/callback', [\App\Modules\Central\Billing\Interface\Http\Controllers\PaguelofacilCallbackController::class, 'handleReturn'])->name('central.billing.paguelofacil.callback');

Route::middleware(['web', 'auth:central'])->group(function () {
    // API Endpoints
    Route::get('/central/plans', [BillingApiController::class, 'listPlans'])->name('central.billing.api.plans');
    Route::post('/central/billing/checkout', [BillingApiController::class, 'checkout'])->name('central.billing.api.checkout');
    Route::get('/central/billing/subscriptions/{tenant_id}', [BillingApiController::class, 'subscriptionStatus'])->name('central.billing.api.subscription_status');
    Route::post('/central/billing/subscriptions/{id}/cancel', [BillingApiController::class, 'cancelSubscription'])->name('central.billing.api.cancel');
    Route::get('/central/billing/invoices', [BillingApiController::class, 'listInvoices'])->name('central.billing.api.invoices');

    // UI & Documents
    Route::get('/central/billing/subscriptions', SubscriptionList::class)->name('central.billing.subscriptions');
    Route::get('/central/billing/plans', \App\Modules\Central\Billing\Interface\Livewire\PlanList::class)->name('central.billing.plans');
    Route::get('/central/billing/plans/create', \App\Modules\Central\Billing\Interface\Livewire\ManagePlan::class)->name('central.billing.plans.create');
    Route::get('/central/billing/plans/{plan}/edit', \App\Modules\Central\Billing\Interface\Livewire\ManagePlan::class)->name('central.billing.plans.edit');
    Route::get('/central/billing/invoices/global', \App\Modules\Central\Billing\Interface\Livewire\GlobalInvoiceList::class)->name('central.billing.invoices.global');
    Route::get('/central/billing/tenants/{tenant}/invoices', TenantInvoiceList::class)->name('central.billing.tenant.invoices');

    Route::get('/central/billing/invoices/{invoice}/pdf', function (Invoice $invoice, GenerateInvoicePdf $action) {
        return $action->download($invoice);
    })->name('central.billing.invoices.pdf');
});
