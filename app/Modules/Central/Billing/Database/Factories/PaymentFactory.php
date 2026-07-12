<?php

namespace App\Modules\Central\Billing\Database\Factories;

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
        $id = (string) Str::uuid();

        return [
            'id' => $id,
            'tenant_id' => null,
            'display_id' => 'INV-'.strtoupper(Str::random(6)),
            'slug' => 'pay-'.Str::random(10),
            'amount' => 99.99,
            'tax_amount' => 0.00,
            'discount' => 0.00,
            'description' => 'Test Payment',
            'email' => 'customer@test.com',
            'currency' => 'USD',
            'status' => 'pending',
            'gateway' => 'CLAVE',
        ];
    }
}
