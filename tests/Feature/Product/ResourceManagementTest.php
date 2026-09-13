<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Resources\Domain\Models\Resource;
use App\Modules\Product\Resources\Interface\Livewire\ManageResources;
use App\Modules\Product\Services\Domain\Models\Service;
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
        'slug' => 'res-'.substr($id, 0, 8),
        'name' => 'Resource Tenant',
        'email' => 'res-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);
});

function createResourceAdmin(): User
{
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $admin->assignRole('admin');

    return $admin;
}

it('creates a staff resource with skills, services and rate (RF14.1–14.4)', function () {
    $this->actingAs(createResourceAdmin());

    $location = Location::factory()->create(['tenant_id' => tenant('id'), 'name' => 'Downtown']);
    $service = Service::factory()->create(['tenant_id' => tenant('id'), 'name' => 'Haircut']);

    Livewire::test(ManageResources::class)
        ->set('name', 'Maria Lopez')
        ->assertSet('slug', 'maria-lopez')
        ->set('type', 'staff')
        ->set('locationId', $location->id)
        ->set('capacity', 1)
        ->set('skills', ['hair coloring', 'balayage'])
        ->set('rate', '30.00')
        ->set('serviceIds', [$service->id])
        ->call('save')
        ->assertHasNoErrors();

    $resource = Resource::where('slug', 'maria-lopez')->first();

    expect($resource)->not->toBeNull()
        ->and($resource->type->value)->toBe('staff')
        ->and($resource->location_id)->toBe($location->id)
        ->and($resource->skills)->toBe(['hair coloring', 'balayage'])
        ->and($resource->rate_cents)->toBe(3000)
        ->and($resource->services->pluck('id')->all())->toBe([$service->id]);
});

it('supports rooms and equipment with capacity (RF14.1)', function () {
    $this->actingAs(createResourceAdmin());

    Livewire::test(ManageResources::class)
        ->set('name', 'Room A')
        ->set('type', 'room')
        ->set('capacity', 4)
        ->call('save')
        ->assertHasNoErrors();

    $resource = Resource::where('slug', 'room-a')->first();

    expect($resource->type->value)->toBe('room')
        ->and($resource->capacity)->toBe(4)
        ->and($resource->location_id)->toBeNull()
        ->and($resource->skills)->toBeNull();
});

it('validates resource input and per-tenant slug uniqueness', function () {
    $this->actingAs(createResourceAdmin());

    Resource::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Existing Resource',
        'slug' => 'existing-resource',
    ]);

    Livewire::test(ManageResources::class)
        ->set('name', '')
        ->set('slug', 'existing-resource')
        ->set('type', 'spaceship')
        ->set('capacity', 0)
        ->set('rate', '-10')
        ->call('save')
        ->assertHasErrors(['name', 'slug', 'type', 'capacity', 'rate']);
});

it('forbids resource management without the resources:manage ability', function () {
    $member = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $member->assignRole('member');

    $this->actingAs($member);

    Livewire::test(ManageResources::class)
        ->set('name', 'Hacked Resource')
        ->call('save')
        ->assertForbidden();
});

it('transfers resources between locations and rejects foreign ones (RF12.3)', function () {
    $this->actingAs(createResourceAdmin());

    $downtown = Location::factory()->create(['tenant_id' => tenant('id'), 'name' => 'Downtown']);
    $uptown = Location::factory()->create(['tenant_id' => tenant('id'), 'name' => 'Uptown']);

    $component = Livewire::test(ManageResources::class)
        ->set('name', 'Juan Perez')
        ->set('locationId', $downtown->id)
        ->call('save')
        ->assertHasNoErrors();

    $resource = Resource::where('slug', 'juan-perez')->first();
    expect($resource->location_id)->toBe($downtown->id);

    // Transfer: reassigning the location moves the resource to the new branch.
    $component->call('edit', $resource->id)
        ->set('locationId', $uptown->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($resource->fresh()->location_id)->toBe($uptown->id);

    // A location from another tenant can never receive the resource.
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

    $foreignLocation = Location::factory()->create(['tenant_id' => $tenantB->id]);

    $component->call('edit', $resource->id)
        ->set('locationId', $foreignLocation->id)
        ->call('save')
        ->assertHasErrors(['locationId']);

    expect($resource->fresh()->location_id)->toBe($uptown->id);
});

it('isolates resources across tenants including slug reuse', function () {
    $tenantAId = tenant('id');

    Resource::factory()->create([
        'tenant_id' => $tenantAId,
        'name' => 'Tenant A Resource',
        'slug' => 'shared-resource-slug',
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

    Livewire::test(ManageResources::class)
        ->set('name', 'Shared Resource Slug')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Shared Resource Slug')
        ->assertDontSee('Tenant A Resource');

    expect(Resource::withoutGlobalScopes()->where('slug', 'shared-resource-slug')->count())->toBe(2);
});

it('soft-deletes and restores resources, rejecting slug conflicts on restore', function () {
    $this->actingAs(createResourceAdmin());

    $component = Livewire::test(ManageResources::class)
        ->set('name', 'Temporary Resource')
        ->call('save')
        ->assertHasNoErrors();

    $original = Resource::where('slug', 'temporary-resource')->first();
    expect($original)->not->toBeNull();

    $component->call('delete', $original->id);
    expect(Resource::find($original->id))->toBeNull();

    Resource::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Replacement Resource',
        'slug' => 'temporary-resource',
    ]);

    $component->call('restore', $original->id)->assertHasErrors(['slug']);

    Resource::where('slug', 'temporary-resource')->where('id', '!=', $original->id)->forceDelete();
    $component->call('restore', $original->id)->assertHasNoErrors();

    expect(Resource::find($original->id))->not->toBeNull();
});

it('returns 404 when mutating a resource that does not exist', function () {
    $this->actingAs(createResourceAdmin());

    expect(fn () => Livewire::test(ManageResources::class)->call('delete', (string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});
