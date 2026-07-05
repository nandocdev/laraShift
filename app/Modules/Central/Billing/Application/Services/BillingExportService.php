<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Services;

use App\Modules\Shared\Contracts\Exportable;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Subscription;

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
