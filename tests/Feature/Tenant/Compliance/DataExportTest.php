<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Application\Jobs\ExportTenantDataJob;
use App\Modules\Tenant\Compliance\Interface\Livewire\DataExport;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $id = (string) Str::uuid();
    $this->tenant = Tenant::create([
        'id' => $id,
        'slug' => 'export-'.substr($id, 0, 8),
        'name' => 'Export Tenant',
        'email' => 'export-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);
    $domain = $this->tenant->slug.'.'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');
    $this->tenant->domains()->create(['domain' => $domain]);
    tenancy()->initialize($this->tenant);
    URL::forceRootUrl('http://'.$domain);

    app(EnsureTenantRolesExist::class)->execute($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->admin->assignRole('admin');
});

it('excludes MFA secrets from the data export', function () {
    Storage::fake('private');

    $user = User::factory()->withTwoFactor()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'mfa-user@example.com',
    ]);

    (new ExportTenantDataJob($this->tenant->id, $user->id))->handle();

    $files = Storage::disk('private')->allFiles('exports');
    expect($files)->toHaveCount(1);

    $content = Storage::disk('private')->get($files[0]);

    expect($content)
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes')
        ->not->toContain('secret')
        ->toContain('mfa-user@example.com');
});

it('prunes exports older than 24 hours and keeps fresh ones', function () {
    Storage::fake('private');

    Storage::disk('private')->put('exports/tenant_data_old.json', '{}');
    Storage::disk('private')->put('exports/tenant_data_fresh.json', '{}');

    touch(
        Storage::disk('private')->path('exports/tenant_data_old.json'),
        now()->subHours(25)->timestamp
    );

    $this->artisan('exports:prune')->assertSuccessful();

    expect(Storage::disk('private')->exists('exports/tenant_data_old.json'))->toBeFalse()
        ->and(Storage::disk('private')->exists('exports/tenant_data_fresh.json'))->toBeTrue();
});

it('queues the export for managers', function () {
    Bus::fake();

    $this->actingAs($this->admin);

    Livewire::test(DataExport::class)
        ->call('export')
        ->assertHasNoErrors();

    Bus::assertDispatched(ExportTenantDataJob::class, fn ($job) => $job->tenantId === $this->tenant->id);
});

it('forbids the export for members without settings:manage', function () {
    $viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $viewer->assignRole('member');

    $this->actingAs($viewer)
        ->get(route('tenant.settings.export'))
        ->assertForbidden();
});
