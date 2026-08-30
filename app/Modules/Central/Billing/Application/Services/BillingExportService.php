<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Services;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Platform\Contracts\Exportable;

class BillingExportService implements Exportable
{
    public function exportToStream($handle): void
    {
        fwrite($handle, '"invoices":[');
        $first = true;
        foreach (Invoice::where('tenant_id', tenant('id'))->cursor() as $invoice) {
            if (! $first) {
                fwrite($handle, ',');
            }
            fwrite($handle, json_encode($invoice));
            $first = false;
        }
        fwrite($handle, '],');

        fwrite($handle, '"subscriptions":[');
        $first = true;
        foreach (Subscription::where('tenant_id', tenant('id'))->cursor() as $sub) {
            if (! $first) {
                fwrite($handle, ',');
            }
            fwrite($handle, json_encode($sub));
            $first = false;
        }
        fwrite($handle, ']');
    }
}
