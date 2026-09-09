<?php

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('does not double-charge on concurrent same display_id', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid(),
        'slug' => 'test-dup-'.Str::random(5),
        'name' => 'Test Tenant',
        'email' => 'dup@example.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
    $data = [
        'tenant_id' => $tenant->id,
        'display_id' => 'sub_X_2026-09',
        'amount' => 1000,
    ];

    // primer insert
    Payment::factory()->create([...$data, 'slug' => Str::uuid(), 'status' => 'approved']);

    // segundo insert debe violar unique(tenant_id,display_id)
    expect(fn () => Payment::factory()->create([...$data, 'slug' => Str::uuid(), 'status' => 'approved']))
        ->toThrow(QueryException::class);
});
