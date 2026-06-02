<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantReactivatedAfterPayment
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $invoiceId
    ) {}
}
