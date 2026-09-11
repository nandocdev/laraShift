<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Platform\Contracts\PlatformBrandingContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class InvoiceDownloadController extends Controller
{
    public function __invoke(string $invoice, PlatformBrandingContract $branding): Response
    {
        $record = Invoice::where('id', $invoice)
            ->where('tenant_id', tenant()->getId())
            ->firstOrFail();

        $tenantModel = config('tenancy.tenant_model');
        $tenantRow = $tenantModel::where('id', $record->tenant_id)->first();

        $pdf = Pdf::loadView('billing::pdf.invoice', [
            'invoice' => $record,
            'platformName' => $branding->name(),
            'billedName' => $tenantRow?->name ?? '',
            'billedEmail' => is_string($tenantRow?->email ?? null) ? $tenantRow->email : '',
            'gateway' => (string) Payment::where('id', $record->payment_id)->value('gateway'),
        ]);

        return $pdf->download("invoice-{$record->id}.pdf");
    }
}
