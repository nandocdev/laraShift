<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Locations\Interface\Livewire\ManageLocations;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'loc-'.substr($id, 0, 8),
        'name' => 'Location Tenant',
        'email' => 'loc-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);
});

function createLocationAdmin(): User
{
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $admin->assignRole('admin');

    return $admin;
}

it('creates a location with full profile data (RF12.1)', function () {
    $this->actingAs(createLocationAdmin());

    Livewire::test(ManageLocations::class)
        ->set('name', 'Downtown Branch')
        ->assertSet('slug', 'downtown-branch')
        ->set('phone', '+507 123-4567')
        ->set('email', 'downtown@biz.com')
        ->set('timezone', 'America/Panama')
        ->set('address.line1', 'Calle 50, Plaza 123')
        ->set('address.city', 'Panama City')
        ->set('address.country', 'PA')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::where('slug', 'downtown-branch')->first();

    expect($location)->not->toBeNull()
        ->and($location->name)->toBe('Downtown Branch')
        ->and($location->timezone)->toBe('America/Panama')
        ->and($location->address['city'])->toBe('Panama City')
        ->and($location->is_active)->toBeTrue();
});

it('supports per-location timezone override with business default fallback (RF12.2)', function () {
    $this->actingAs(createLocationAdmin());

    Livewire::test(ManageLocations::class)
        ->set('name', 'Branch Without Timezone')
        ->set('timezone', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Location::where('slug', 'branch-without-timezone')->first()->timezone)->toBeNull();
});

it('validates location input and per-tenant slug uniqueness', function () {
    $this->actingAs(createLocationAdmin());

    Location::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Existing Branch',
        'slug' => 'existing-branch',
    ]);

    Livewire::test(ManageLocations::class)
        ->set('name', '')
        ->set('slug', 'existing-branch')
        ->set('email', 'not-an-email')
        ->set('timezone', 'Mars/Olympus')
        ->set('address.country', 'PANAMA')
        ->call('save')
        ->assertHasErrors(['name', 'slug', 'email', 'timezone', 'address.country']);
});

it('forbids location management without the locations:manage ability', function () {
    $member = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $member->assignRole('member');

    $this->actingAs($member);

    Livewire::test(ManageLocations::class)
        ->set('name', 'Hacked Branch')
        ->call('save')
        ->assertForbidden();
});

it('isolates locations across tenants including slug reuse', function () {
    $tenantAId = tenant('id');

    Location::factory()->create([
        'tenant_id' => $tenantAId,
        'name' => 'Tenant A Branch',
        'slug' => 'shared-slug',
    ]);

    $idB = (string) Str::uuid();
    $tenantB = Tenant::create([
        'id' => $idB,
        'slug' => 'other-'.substr($idB, 0, 8),
        'name' => 'Other Tenant',
        'email' => 'other-'.substr($idB, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);
    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenantB->domains()->create(['domain' => $tenantB->slug.'.'.$centralDomain]);
    app(EnsureTenantRolesExist::class)->execute($tenantB);

    tenancy()->end();
    tenancy()->initialize($tenantB);

    $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);

    setPermissionsTeamId($tenantB->id);
    $adminB->assignRole('admin');

    $this->actingAs($adminB);

    // Same slug is valid in another tenant, and A's rows stay invisible.
    Livewire::test(ManageLocations::class)
        ->set('name', 'Shared Slug')
        ->assertSet('slug', 'shared-slug')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Shared Slug')
        ->assertDontSee('Tenant A Branch');

    expect(Location::withoutGlobalScopes()->where('slug', 'shared-slug')->count())->toBe(2);
});

it('soft-deletes and restores locations, rejecting slug conflicts on restore', function () {
    $this->actingAs(createLocationAdmin());

    $component = Livewire::test(ManageLocations::class)
        ->set('name', 'Temporary Branch')
        ->call('save')
        ->assertHasNoErrors();

    $original = Location::where('slug', 'temporary-branch')->first();
    expect($original)->not->toBeNull();

    $component->call('delete', $original->id);
    expect(Location::find($original->id))->toBeNull();

    // Reusing the freed slug blocks the restore with a field error, never a 500.
    Location::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Replacement Branch',
        'slug' => 'temporary-branch',
    ]);

    $component->call('restore', $original->id)->assertHasErrors(['slug']);

    // Free the slug and restore succeeds.
    Location::where('slug', 'temporary-branch')->where('id', '!=', $original->id)->forceDelete();
    $component->call('restore', $original->id)->assertHasNoErrors();

    expect(Location::find($original->id))->not->toBeNull();
});

it('returns 404 when mutating a location that does not exist', function () {
    $this->actingAs(createLocationAdmin());

    expect(fn () => Livewire::test(ManageLocations::class)->call('delete', (string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});
