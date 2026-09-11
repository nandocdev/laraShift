<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Modules\Central\Growth\Interface\Livewire\RegisterTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('can advance through the wizard steps', function () {
    Livewire::test(RegisterTenant::class)
        // Step 1
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

it('can register a tenant from step 2 without rules exception', function () {
    Livewire::test(RegisterTenant::class)
        ->set('step', 2)
        ->set('name', 'John Doe')
        ->set('company', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
        ->call('register')
        ->assertHasNoErrors()
        ->assertStatus(200);
});

it('fails validation in register if any field is missing', function () {
    Livewire::test(RegisterTenant::class)
        ->set('step', 2)
        ->set('company', '') // Empty company
        ->set('slug', 'acme-corp')
        ->set('email', 'admin@acme.com')
        ->set('password', 'Password123!')
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
