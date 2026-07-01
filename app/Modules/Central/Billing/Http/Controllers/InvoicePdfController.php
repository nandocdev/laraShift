<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Central\Billing\Actions\GenerateInvoicePdfAction;
use App\Modules\Central\Billing\Models\Invoice;
use Illuminate\Http\Response;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice, GenerateInvoicePdfAction $action): Response
    {
        return $action->download($invoice);
    }
}
