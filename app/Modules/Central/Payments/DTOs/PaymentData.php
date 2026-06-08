<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;

final class PaymentData extends Data {
    public function __construct(
        #[Numeric, Min(0.01)]
        public readonly float $amount,

        public readonly string $description,

        /** Your internal order/invoice reference */
        public readonly string $displayId,

        public readonly string $email,

        public readonly float $taxAmount = 0.0,

        public readonly float $discount = 0.0,

        /** ISO language code: 'es' | 'en' */
        public readonly string $lang = 'es',

        /** Transaction channel. Default: cclw (Clave web) */
        public readonly string $txChannel = 'cclw',

        /** Arbitrary key-value pairs for the gateway */
        public readonly array $customFieldValues = [],

        /** Unique slug for this checkout session. Auto-generated if empty. */
        public readonly ?string $slug = null,
    ) {
    }

    public function resolvedSlug(): string {
        return $this->slug ?? 'clave_' . now()->getTimestampMs();
    }

    public function netAmount(): float {
        return round($this->amount - $this->discount + $this->taxAmount, 2);
    }
}
