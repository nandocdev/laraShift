<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Database\Factories;

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'slug' => 'pay_'.Str::lower(Str::random(12)),
            'display_id' => 'DSP-'.strtoupper(Str::random(10)),
            'amount_cents' => 2900,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
            'gateway' => 'clave',
            'provider_metadata' => [],
        ];
    }
}
