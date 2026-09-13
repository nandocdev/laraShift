<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Product\Services\Domain\Models\ServiceCategory;
use App\Modules\Product\Services\Interface\Livewire\ManageServices;
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
        'slug' => 'svc-'.substr($id, 0, 8),
        'name' => 'Service Tenant',
        'email' => 'svc-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);
});

function createServiceAdmin(): User
{
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $admin->assignRole('admin');

    return $admin;
}

it('creates a service with category, deposit and cancellation policy (RF13.1–13.5)', function () {
    $this->actingAs(createServiceAdmin());

    $category = ServiceCategory::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Hair',
        'slug' => 'hair',
    ]);

    $location = Location::factory()->create(['tenant_id' => tenant('id')]);

    Livewire::test(ManageServices::class)
        ->set('name', 'Haircut')
        ->assertSet('slug', 'haircut')
        ->set('categoryId', $category->id)
        ->set('description', 'Modern cut with wash.')
        ->set('durationMinutes', 45)
        ->set('capacity', 1)
        ->set('bufferBeforeMinutes', 5)
        ->set('bufferAfterMinutes', 10)
        ->set('price', '25.50')
        ->set('depositType', 'percentage')
        ->set('depositValue', '20')
        ->set('cancellationWindowHours', '24')
        ->call('addCancellationRule')
        ->set('cancellationRules.0.min_hours', '48')
        ->set('cancellationRules.0.refund_percent', '100')
        ->set('locationIds', [$location->id])
        ->call('saveService')
        ->assertHasNoErrors();

    $service = Service::where('slug', 'haircut')->first();

    expect($service)->not->toBeNull()
        ->and($service->category_id)->toBe($category->id)
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->price_cents)->toBe(2550)
        ->and($service->deposit_type->value)->toBe('percentage')
        ->and($service->deposit_value)->toBe(20)
        ->and($service->cancellation_policy)->toBe([['min_hours' => 48, 'refund_percent' => 100]])
        ->and($service->locations->pluck('id')->all())->toBe([$location->id]);
});

it('supports group services with multiple capacity (RF13.6)', function () {
    $this->actingAs(createServiceAdmin());

    Livewire::test(ManageServices::class)
        ->set('name', 'Yoga Group Class')
        ->set('durationMinutes', 60)
        ->set('capacity', 20)
        ->set('price', '10')
        ->call('saveService')
        ->assertHasNoErrors();

    expect(Service::where('slug', 'yoga-group-class')->first()->capacity)->toBe(20);
});

it('validates service input, deposit coherence and per-tenant slug uniqueness', function () {
    $this->actingAs(createServiceAdmin());

    Service::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Existing Service',
        'slug' => 'existing-service',
    ]);

    Livewire::test(ManageServices::class)
        ->set('name', '')
        ->set('slug', 'existing-service')
        ->set('durationMinutes', 0)
        ->set('capacity', 0)
        ->set('price', '-5')
        ->call('saveService')
        ->assertHasErrors(['name', 'slug', 'durationMinutes', 'capacity', 'price']);

    // Deposit coherence runs after base validation passes, so each case
    // needs otherwise valid input.
    Livewire::test(ManageServices::class)
        ->set('name', 'Percentage Deposit Service')
        ->set('durationMinutes', 30)
        ->set('price', '20')
        ->set('depositType', 'percentage')
        ->set('depositValue', '150')
        ->call('saveService')
        ->assertHasErrors(['depositValue']);

    Livewire::test(ManageServices::class)
        ->set('name', 'Fixed Deposit Service')
        ->set('durationMinutes', 30)
        ->set('price', '50')
        ->set('depositType', 'fixed')
        ->set('depositValue', 'not-a-number')
        ->call('saveService')
        ->assertHasErrors(['depositValue']);
});

it('forbids service management without the services:manage ability', function () {
    $member = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $member->assignRole('member');

    $this->actingAs($member);

    Livewire::test(ManageServices::class)
        ->set('name', 'Hacked Service')
        ->call('saveService')
        ->assertForbidden();
});

it('isolates services and categories across tenants', function () {
    $tenantAId = tenant('id');

    Service::factory()->create([
        'tenant_id' => $tenantAId,
        'name' => 'Tenant A Service',
        'slug' => 'shared-service-slug',
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

    Livewire::test(ManageServices::class)
        ->set('name', 'Shared Service Slug')
        ->set('durationMinutes', 30)
        ->set('price', '15')
        ->call('saveService')
        ->assertHasNoErrors()
        ->assertSee('Shared Service Slug')
        ->assertDontSee('Tenant A Service');

    expect(Service::withoutGlobalScopes()->where('slug', 'shared-service-slug')->count())->toBe(2);
});

it('rejects assigning locations from another tenant', function () {
    $tenantAId = tenant('id');

    $foreignLocation = Location::factory()->create(['tenant_id' => $tenantAId]);

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

    Livewire::test(ManageServices::class)
        ->set('name', 'Cross Tenant Service')
        ->set('durationMinutes', 30)
        ->set('price', '15')
        ->set('locationIds', [$foreignLocation->id])
        ->call('saveService')
        ->assertHasErrors(['locationIds.0']);

    expect(Service::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count())->toBe(0);
});

it('manages categories and nullifies them on services when deleted', function () {
    $this->actingAs(createServiceAdmin());

    $component = Livewire::test(ManageServices::class)
        ->set('categoryName', 'Nails')
        ->assertSet('categorySlug', 'nails')
        ->set('categoryColor', '#10b981')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $category = ServiceCategory::where('slug', 'nails')->first();
    expect($category)->not->toBeNull();

    Livewire::test(ManageServices::class)
        ->set('name', 'Manicure')
        ->set('categoryId', $category->id)
        ->set('durationMinutes', 60)
        ->set('price', '30')
        ->call('saveService')
        ->assertHasNoErrors();

    expect(Service::where('slug', 'manicure')->first()->category_id)->toBe($category->id);

    $component->call('deleteCategory', $category->id);

    expect(ServiceCategory::find($category->id))->toBeNull()
        ->and(Service::where('slug', 'manicure')->first()->category_id)->toBeNull();
});

it('returns 404 when mutating a service that does not exist', function () {
    $this->actingAs(createServiceAdmin());

    expect(fn () => Livewire::test(ManageServices::class)->call('deleteService', (string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});
