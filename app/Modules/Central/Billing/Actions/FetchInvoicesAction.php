<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Invoice;

final readonly class FetchInvoicesAction
{
    public function execute(?string $tenantId = null): array
    {
        $query = Invoice::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->latest()->paginate(20)->toArray();
    }
}
