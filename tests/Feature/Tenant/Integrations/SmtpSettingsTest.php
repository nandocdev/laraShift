<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Integrations\Interface\Livewire\SmtpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'test-'.substr($id, 0, 8),
        'name' => 'Test Tenant',
        'email' => 'test-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);
});

test('renders the smtp settings page', function () {
    app(EnsureTenantRolesExist::class)->execute(tenant());
    setPermissionsTeamId(tenant('id'));

    $user = User::factory()->create(['tenant_id' => tenant('id'), 'status' => 'active']);
    $user->assignRole('admin');

    $this->actingAs($user);

    Livewire::test(SmtpSettings::class)
        ->assertSuccessful();
});
