<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Settings\Support\CentralBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final readonly class GenerateInvoicePdfAction
{
    public function execute(Invoice $invoice): string
    {
        $tenant = $invoice->tenant;

        $pdf = Pdf::loadView('billing::pdf.invoice-proforma', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'platformName' => CentralBranding::platformName(),
            'primaryColor' => CentralBranding::primaryColor(),
            'logoUrl' => CentralBranding::logoUrl(),
        ]);

        return $pdf->output();
    }

    public function download(Invoice $invoice): Response
    {
        $filename = "proforma-{$invoice->number}.pdf";

        return response($this->execute($invoice))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
