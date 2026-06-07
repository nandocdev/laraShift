<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Modules\Central\Billing\Models\LedgerEntry;
use App\Modules\Central\Billing\Livewire\LedgerAudit;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected to central login', function () {
    $response = $this->get('/central/billing/ledger');
    $response->assertRedirect('/login');
});

test('authenticated central users can visit the ledger audit page', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central');

    $response = $this->get('/central/billing/ledger');
    $response->assertOk();
});

test('ledger audit page lists ledger entries', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central');

    // Create the tenant first
    Tenant::create([
        'id' => '00000000-0000-0000-0000-0000000000ac',
        'slug' => 'acme-tenant',
        'name' => 'Acme Tenant',
        'email' => 'acme@test.com',
        'plan_id' => 'pro',
    ]);

    // Create a dummy ledger entry
    $entry = LedgerEntry::create([
        'tenant_id' => '00000000-0000-0000-0000-0000000000ac',
        'type' => 'CREDIT',
        'amount' => 150.00,
        'currency' => 'USD',
        'description' => 'Test Transaction Ledger Log',
        'reference_type' => 'App\Models\Order',
        'reference_id' => Str::uuid()->toString(),
    ]);

    Livewire::test(LedgerAudit::class)
        ->assertSee('Test Transaction Ledger Log')
        ->assertSee('00000000-0000-0000-0000-0000000000ac')
        ->assertSee('+150.00 USD');
});
