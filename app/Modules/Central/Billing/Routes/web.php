<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Actions\GenerateInvoicePdfAction;
use App\Modules\Central\Billing\Http\Controllers\StripeWebhookController;
use App\Modules\Central\Billing\Livewire\SubscriptionList;
use App\Modules\Central\Billing\Livewire\TenantInvoiceList;
use App\Modules\Central\Billing\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::post('/central/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])->name('central.billing.webhook.stripe');

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/billing/subscriptions', SubscriptionList::class)->name('central.billing.subscriptions');
    Route::get('/central/billing/tenants/{tenant}/invoices', TenantInvoiceList::class)->name('central.billing.tenant.invoices');
    
    Route::get('/central/billing/invoices/{invoice}/pdf', function (Invoice $invoice, GenerateInvoicePdfAction $action) {
        return $action->download($invoice);
    })->name('central.billing.invoices.pdf');
    
    // Placeholder routes for checkout success/cancel
    Route::get('/central/billing/success/{tenant}', function () {
        return 'Success';
    })->name('central.billing.success');

    Route::get('/central/billing/cancel/{tenant}', function () {
        return 'Cancelled';
    })->name('central.billing.cancel');
});
