<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Database\Factories;

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Provisioning\Models\Tenant;
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
            'tenant_id' => function () {
                $tenant = Tenant::create([
                    'id' => (string) Str::uuid(),
                    'slug' => 'tenant-'.Str::random(6),
                    'name' => 'Factory Tenant',
                    'email' => 'factory-'.Str::random(6).'@test.com',
                    'plan_id' => 'free',
                    'status' => 'active',
                ]);
                $tenant->domains()->create(['domain' => $tenant->slug.'.localhost']);

                return $tenant->id;
            },
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
