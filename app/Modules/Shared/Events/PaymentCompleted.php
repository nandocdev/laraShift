<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento unificado emitido cuando un pago alcanza un estado terminal.
 * Reemplaza los eventos legacy PaymentApproved y PaymentDeclined.
 */
final class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $displayId,
        public readonly PaymentContext $context,
        public readonly PaymentStatus $status,
        public readonly float $amount,
        public readonly ?string $gatewayReference = null,
        public readonly array $metadata = [],
    ) {}
}
