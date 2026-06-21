<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Modules\Central\Marketing\Livewire\RegisterTenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    \Illuminate\Support\Facades\Cache::flush();
});

it('can advance through the wizard steps', function () {
    $component = Livewire::test(RegisterTenant::class)
        // Step 1
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        
        // Step 2
        ->set('plan_id', 'pro')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);
});

it('can register a tenant from step 3 without rules exception', function () {
    Livewire::test(RegisterTenant::class)
        ->set('step', 3)
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->set('plan_id', 'free')
        ->call('register')
        ->assertHasNoErrors()
        ->assertStatus(200);
});

it('fails validation in register if any field is missing', function () {
    Livewire::test(RegisterTenant::class)
        ->set('step', 3)
        ->set('company', '') // Empty company
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->set('plan_id', 'free')
        ->call('register')
        ->assertHasErrors(['company' => 'required']);
});

it('autogenerates slug from company name', function () {
    Livewire::test(RegisterTenant::class)
        ->set('company', 'My Awesome Company')
        ->assertSet('slug', 'my-awesome-company');
});

it('stops autogenerating slug if slug is manually modified', function () {
    Livewire::test(RegisterTenant::class)
        ->set('company', 'Initial Company')
        ->assertSet('slug', 'initial-company')
        ->set('slug', 'custom-slug')
        ->set('company', 'New Company')
        ->assertSet('slug', 'custom-slug');
});

it('locks the slug on nextStep and blocks other users', function () {
    // User 1 goes to Step 2, locking 'acme-corp'
    Livewire::test(RegisterTenant::class)
        ->set('name', 'User One')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'one@acme.com')
        ->set('password', 'Password123!')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    // User 2 tries to use 'acme-corp' and should get blocked
    Livewire::test(RegisterTenant::class)
        ->set('name', 'User Two')
        ->set('company', 'Acme Corp 2')
        ->set('slug', 'acme-corp')
        ->set('email', 'two@acme.com')
        ->set('password', 'Password123!')
        ->call('nextStep')
        ->assertHasErrors(['slug']);
});

it('releases the slug lock upon successful registration', function () {
    // User 1 registers and creates the tenant, which should release the lock
    Livewire::test(RegisterTenant::class)
        ->set('step', 3)
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->set('plan_id', 'free')
        ->call('register')
        ->assertHasNoErrors();

    // The lock is deleted
    $lockKey = 'reserved_slug_acme-corp';
    expect(\Illuminate\Support\Facades\Cache::has($lockKey))->toBeFalse();
});

it('allows the same user to navigate the wizard without auto-blocking', function () {
    $component = Livewire::test(RegisterTenant::class)
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    // Go back to step 1
    $component->call('previousStep')
        ->assertSet('step', 1);

    // Try to advance again with the same slug/email - should not block
    $component->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

it('requires a strong password for tenant registration', function (string $password) {
    Livewire::test(RegisterTenant::class)
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', $password)
        ->call('nextStep')
        ->assertHasErrors(['password']);
})->with([
    'short' => 'Pass1!',
    'no numbers' => 'Password!',
    'no symbols' => 'Password123',
    'no mixed case' => 'password123!',
    'all numbers' => '12345678!',
]);

it('detects an already approved payment and allows retry without payment token', function () {
    // 1. Manually seed an approved payment for the given slug & email combination
    $slug = 'acme-corp';
    $email = 'admin@acme.com';
    $checkoutSlug = 'checkout_' . md5($slug . $email);
    
    \App\Modules\Central\Payments\Models\Payment::create([
        'tenant_id' => \Illuminate\Support\Str::uuid()->toString(),
        'display_id' => 'SUB-123456',
        'slug' => $checkoutSlug,
        'amount' => 29.0, // e.g. pro plan price
        'description' => 'Subscription for pro',
        'email' => $email,
        'currency' => 'USD',
        'status' => 'approved',
        'gateway' => 'dlocal',
    ]);

    // 2. Test the Livewire wizard
    $component = Livewire::test(RegisterTenant::class)
        ->set('step', 3)
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', $slug)
        ->set('email', $email)
        ->set('password', 'Password123!')
        ->set('plan_id', 'pro') // Paid plan
        // Note: payment_token is NOT set!
        ->assertSet('paymentAlreadyApproved', true); // Assert it dynamically detected the approved payment

    // 3. Trigger register - should succeed and not require payment_token
    $component->call('register')
        ->assertHasNoErrors();
});



