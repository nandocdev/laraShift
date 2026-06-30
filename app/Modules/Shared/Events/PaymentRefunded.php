<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

class PaymentRefunded extends DomainEvent
{
    public function __construct(
        public string $tenantId,
        public string $displayId,
        public float $amount,
        public string $reason,
    ) {
        parent::__construct(version: 1);
        $this->tenantId = $tenantId;
    }

    public static function eventType(): string
    {
        return 'payment_refunded';
    }
}
