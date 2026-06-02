<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions a tenant atomically and dispatches the event', function () {
    Event::fake([TenantProvisioned::class]);

    $action = app(CreateTenantAction::class);
    $data = new CreateTenantData(
        name: 'Acme Corp',
        slug: 'acme',
        email: 'admin@acme.com',
    );

    $tenant = $action->execute($data);

    expect($tenant)->toBeInstanceOf(Tenant::class);
    expect($tenant->name)->toBe('Acme Corp');
    expect($tenant->domains)->toHaveCount(1);
    expect($tenant->domains->first()->domain)->toBe('acme.larashift.test');

    Event::assertDispatched(TenantProvisioned::class, function ($event) use ($tenant) {
        return $event->tenant->id === $tenant->id && $event->adminEmail === 'admin@acme.com';
    });
});

it('creates the initial admin user via the listener', function () {
    $action = app(CreateTenantAction::class);
    $data = new CreateTenantData(
        name: 'Acme Corp',
        slug: 'acme',
        email: 'admin@acme.com',
    );

    $tenant = $action->execute($data);

    $tenant->run(function () {
        $user = User::where('email', 'admin@acme.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('Administrator');
    });
});
