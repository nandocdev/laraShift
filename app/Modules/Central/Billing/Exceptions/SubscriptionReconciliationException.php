<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Exceptions;

class SubscriptionReconciliationException extends \RuntimeException
{
    public static function gatewayError(string $tenantId, string $message): self
    {
        return new self("Reconciliation failed for tenant {$tenantId}: {$message}");
    }
}
