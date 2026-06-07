<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Plinth\MultiTenantBilling\Core\Models\LedgerEntry as BaseLedgerEntry;

class LedgerEntry extends BaseLedgerEntry
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'type',
        'amount',
        'currency',
        'description',
        'reference_type',
        'reference_id',
    ];
}
