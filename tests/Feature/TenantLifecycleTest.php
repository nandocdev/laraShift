<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Actions\ArchiveTenantAction;
use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\Actions\DeleteTenantAction;
use App\Modules\Central\Provisioning\Actions\SwitchMaintenanceModeAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(CreateTenantAction::class);
    $this->data = new CreateTenantData(
        name: 'Acme Corp',
        slug: 'acme',
        email: 'admin@acme.com',
        plan_id: 'free',
    );
});

it('can put a tenant in maintenance mode', function () {
    $tenant = $this->action->execute($this->data);
    $switchAction = app(SwitchMaintenanceModeAction::class);

    $switchAction->execute($tenant, true);

    expect($tenant->fresh()->maintenance_mode)->toBeTrue();
    expect($tenant->fresh()->status)->toBe('maintenance');
});

it('can archive a tenant', function () {
    $tenant = $this->action->execute($this->data);
    $archiveAction = app(ArchiveTenantAction::class);

    $archiveAction->execute($tenant);

    expect($tenant->fresh()->status)->toBe('archived');
    expect($tenant->fresh()->archived_at)->not->toBeNull();
    expect($tenant->fresh()->read_only)->toBeTrue();
});

it('can soft delete a tenant', function () {
    $tenant = $this->action->execute($this->data);
    $deleteAction = app(DeleteTenantAction::class);

    $deleteAction->execute($tenant);

    expect(Tenant::find($tenant->id))->toBeNull();
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();
});

it('middleware blocks access for maintenance mode', function () {
    $tenant = $this->action->execute($this->data);
    $tenant->update(['maintenance_mode' => true]);

    // Mock tenant initialization
    config(['tenancy.tenant_model' => Tenant::class]);
    tenancy()->initialize($tenant);

    Route::get('/test-tenant', fn () => 'ok')->middleware(EnsureTenantIsActive::class);

    $response = $this->get('/test-tenant');

    $response->assertStatus(503);
});

it('middleware returns 404 for archived tenants', function () {
    $tenant = $this->action->execute($this->data);
    $tenant->update(['status' => 'archived']);

    tenancy()->initialize($tenant);

    Route::get('/test-tenant-archived', fn () => 'ok')->middleware(EnsureTenantIsActive::class);

    $response = $this->get('/test-tenant-archived');

    $response->assertStatus(404);
});
