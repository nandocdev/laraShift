<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Services;

use App\Modules\Shared\Contracts\Exportable;
use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Subscription;

class BillingExportService implements Exportable
{
    public function getExportData(): array
    {
        return [
            'invoices' => Invoice::where('tenant_id', tenant('id'))->get()->toArray(),
            'subscriptions' => Subscription::where('tenant_id', tenant('id'))->get()->toArray(),
        ];
    }
}
