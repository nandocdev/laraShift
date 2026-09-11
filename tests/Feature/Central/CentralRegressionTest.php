<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Growth\Interface\Livewire\RegisterTenant;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\ImpersonateTenantAction;
use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // CentralUser no tiene roles en tests: abrir Gates solo aquí (prod usa roles reales)
    Gate::define('support:impersonate', fn () => true);
});

function makeCentralTenant(string $slug = 'acme'): Tenant
{
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => $slug,
        'name' => 'Acme',
        'email' => $slug.'@test.com',
        'status' => 'active',
    ]);
    $tenant->domains()->create(['domain' => $slug.'.localhost']);

    return $tenant;
}

function makeCentralAdmin(): CentralUser
{
    return CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin-'.Str::random(6).'@test.com',
        'password' => 'password',
    ]);
}

// SU001 — impersonation: hash en DB, https en URL, reason corta rechazada
it('hashes impersonation token and returns https url', function () {
    $admin = makeCentralAdmin();
    $this->actingAs($admin, 'central');
    $tenant = makeCentralTenant('imp-'.Str::random(4));

    $url = app(ImpersonateTenantAction::class)->execute($tenant, 'ticket-123 soporte necesario urgente');

    expect($url)->toStartWith('https://');
    expect($url)->toContain('/support/auth?token=');

    $plain = Str::after($url, 'token=');
    $stored = SupportSession::where('tenant_id', $tenant->id)->firstOrFail();

    // Nunca plaintext en DB
    expect($stored->token)->not->toBe($plain);
    expect($stored->token)->toBe(hash('sha256', $plain));
    // TTL 30 min, no 2h
    expect($stored->expires_at->diffInMinutes($stored->started_at))->toBeLessThanOrEqual(31);
});

it('rejects impersonation with short reason', function () {
    $admin = makeCentralAdmin();
    $this->actingAs($admin, 'central');
    $tenant = makeCentralTenant('imp2-'.Str::random(4));

    expect(fn () => app(ImpersonateTenantAction::class)->execute($tenant, 'corto'))
        ->toThrow(InvalidArgumentException::class);
});

// G003+G006 — slug race mapeado a error de campo, honeypot y password limpiado
it('maps slug race to field error and clears password', function () {
    makeCentralTenant('taken-slug');

    Livewire::test(RegisterTenant::class)
        ->set('step', 2)
        ->set('name', 'John')
        ->set('company', 'Taken Slug')
        ->set('slug', 'taken-slug')
        ->set('email', 'fresh-'.Str::random(6).'@test.com')
        ->set('password', 'Password123!')
        ->call('register')
        ->assertHasErrors(['slug']);
});

it('rejects honeypot spam without creating tenant', function () {
    $email = 'spam-'.Str::random(6).'@test.com';

    Livewire::test(RegisterTenant::class)
        ->set('step', 2)
        ->set('name', 'Bot')
        ->set('company', 'Bot Corp')
        ->set('slug', 'bot-'.Str::random(6))
        ->set('email', $email)
        ->set('password', 'Password123!')
        ->set('honeypot', 'i-am-bot')
        ->call('register')
        ->assertHasErrors(['honeypot']);

    expect(Tenant::where('email', $email)->exists())->toBeFalse();
});
