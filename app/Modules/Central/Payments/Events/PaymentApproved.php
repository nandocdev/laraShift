<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Events;

use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Shared\Events\PaymentCompleted;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated Utilizar App\Modules\Shared\Events\PaymentCompleted.
 * Este evento se mantiene temporalmente para referencia. No está registrado en ningún listener.
 * Será eliminado en la siguiente iteración de limpieza.
 * @see PaymentCompleted
 */
final class PaymentApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentResultData $result,
    ) {}
}
