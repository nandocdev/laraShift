<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Provisioning\Models\Tenant;
use Laravel\Cashier\Invoice as CashierInvoice;

final readonly class SyncInvoicesAction
{
    public function execute(Tenant $tenant): void
    {
        $tenant->invoices()->each(function (CashierInvoice $cashierInvoice) use ($tenant) {
            Invoice::updateOrCreate(
                ['external_id' => $cashierInvoice->id],
                [
                    'tenant_id' => $tenant->id,
                    'number' => $cashierInvoice->number,
                    'status' => $cashierInvoice->status,
                    'amount_due' => $cashierInvoice->amount_due,
                    'amount_paid' => $cashierInvoice->amount_paid,
                    'currency' => $cashierInvoice->currency,
                    'period_start' => $cashierInvoice->period_start,
                    'period_end' => $cashierInvoice->period_end,
                    'pdf_url' => $cashierInvoice->invoice_pdf,
                ]
            );
        });
    }
}
