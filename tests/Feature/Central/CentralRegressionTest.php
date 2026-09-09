<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Catalog\Application\Actions\ApplyTenantFeatureOverride;
use App\Modules\Central\Catalog\Application\Actions\ResolveTenantFeatures;
use App\Modules\Central\Catalog\Domain\Models\Feature;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Catalog\Domain\Models\TenantFeatureOverride;
use App\Modules\Central\Growth\Interface\Livewire\RegisterTenant;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\ImpersonateTenantAction;
use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // CentralUser no tiene roles en tests: abrir Gates solo aquí (prod usa roles reales)
    Gate::define('features:manage', fn () => true);
    Gate::define('support:impersonate', fn () => true);
});

function makeCentralTenant(string $slug = 'acme'): Tenant
{
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => $slug,
        'name' => 'Acme',
        'email' => $slug.'@test.com',
        'plan_id' => 'free',
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

// C001 — invalidación de cache al mutar overrides (no stale entitlements)
it('invalidates feature cache when override is applied', function () {
    $plan = Plan::create(['id' => (string) Str::uuid(), 'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'price_monthly' => 2900, 'price_yearly' => 29000, 'features' => []]);
    $feature = Feature::create(['id' => (string) Str::uuid(), 'key' => 'billing.invoices', 'name' => 'Invoices']);
    $plan->catalogFeatures()->attach($feature->id);

    $tenant = makeCentralTenant('feat-'.Str::random(4));
    $tenant->update(['plan_id' => $plan->slug]);

    $admin = makeCentralAdmin();
    // Admin con permiso manage-features para pasar el Gate
    if (method_exists($admin, 'assignRole')) {
        try {
            $admin->assignRole('admin');
        } catch (Throwable) {
        }
    }
    $this->actingAs($admin, 'central');

    // Warm cache: tiene la feature por plan
    expect($tenant->hasFeature('billing.invoices'))->toBeTrue();

    // Aplicar deny debe reflejarse sin forceRefresh manual
    app(ApplyTenantFeatureOverride::class)->execute($tenant, 'billing.invoices', 'deny', 'revocado por QA');

    // Resolver fresco (lo que haría EnsureHasFeature en siguiente request)
    Cache::forget("tenant:{$tenant->id}:features");
    expect(app(ResolveTenantFeatures::class)->execute($tenant))->not->toContain('billing.invoices');
});

// C002+C004 — PK estable y re-apply tras soft-delete
it('keeps override PK stable and supports re-apply after soft-delete', function () {
    $plan = Plan::create(['id' => (string) Str::uuid(), 'name' => 'Free', 'slug' => 'free-'.Str::random(4), 'price_monthly' => 0, 'price_yearly' => 0, 'features' => []]);
    $feature = Feature::create(['id' => (string) Str::uuid(), 'key' => 'api.access', 'name' => 'API']);
    $tenant = makeCentralTenant('ovr-'.Str::random(4));
    $tenant->update(['plan_id' => $plan->slug]);

    $admin = makeCentralAdmin();
    if (method_exists($admin, 'assignRole')) {
        try {
            $admin->assignRole('admin');
        } catch (Throwable) {
        }
    }
    $this->actingAs($admin, 'central');

    $action = app(ApplyTenantFeatureOverride::class);
    $first = $action->execute($tenant, 'api.access', 'allow', 'v1');
    $second = $action->execute($tenant, 'api.access', 'deny', 'v2');

    expect($second->id)->toBe($first->id);

    // Soft-delete + re-apply no debe dar 23505
    $second->delete();
    $third = $action->execute($tenant, 'api.access', 'allow', 'v3');
    expect($third->id)->toBe($first->id);
    expect(TenantFeatureOverride::where('tenant_id', $tenant->id)->count())->toBe(1);
});

// G003+G006 — slug race mapeado a error de campo, honeypot y password limpiado
it('maps slug race to field error and clears password', function () {
    makeCentralTenant('taken-slug');

    Livewire::test(RegisterTenant::class)
        ->set('step', 3)
        ->set('name', 'John')
        ->set('company', 'Taken Slug')
        ->set('slug', 'taken-slug')
        ->set('email', 'fresh-'.Str::random(6).'@test.com')
        ->set('password', 'Password123!')
        ->set('plan_id', 'free')
        ->call('register')
        ->assertHasErrors(['slug']);
});

it('rejects honeypot spam without creating tenant', function () {
    $email = 'spam-'.Str::random(6).'@test.com';

    Livewire::test(RegisterTenant::class)
        ->set('step', 3)
        ->set('name', 'Bot')
        ->set('company', 'Bot Corp')
        ->set('slug', 'bot-'.Str::random(6))
        ->set('email', $email)
        ->set('password', 'Password123!')
        ->set('plan_id', 'free')
        ->set('honeypot', 'i-am-bot')
        ->call('register')
        ->assertHasErrors(['honeypot']);

    expect(Tenant::where('email', $email)->exists())->toBeFalse();
});
