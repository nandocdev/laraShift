<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Plinth\MultiTenantBilling\Core\Models\LedgerEntry;
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

    // Create a dummy ledger entry
    $entry = LedgerEntry::create([
        'tenant_id' => 'acme-tenant',
        'type' => 'CREDIT',
        'amount' => 150.00,
        'currency' => 'USD',
        'description' => 'Test Transaction Ledger Log',
        'reference_type' => 'App\Models\Order',
        'reference_id' => 123,
    ]);

    Livewire::test(LedgerAudit::class)
        ->assertSee('Test Transaction Ledger Log')
        ->assertSee('acme-tenant')
        ->assertSee('+150.00 USD');
});
