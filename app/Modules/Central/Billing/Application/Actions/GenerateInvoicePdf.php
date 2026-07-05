<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Operations\Infrastructure\Support\CentralBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final readonly class GenerateInvoicePdf
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

    public function download(Invoice $invoice): \Illuminate\Http\Response
    {
        $filename = "proforma-{$invoice->number}.pdf";
        
        return response($this->execute($invoice))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
