<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Jobs\PurgeTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant as CentralTenant;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Domain\Models\UserMfa;
use App\Modules\Tenant\Workspace\Interface\Livewire\CloseWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

function tenantRow(string $id): ?object
{
    return DB::table('tenants')->where('id', $id)->first();
}

beforeEach(function () {
    $id = (string) Str::uuid();
    $this->tenant = CentralTenant::create([
        'id' => $id,
        'slug' => 'close-'.substr($id, 0, 8),
        'name' => 'Close Tenant',
        'email' => 'close-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);
    $domain = $this->tenant->slug.'.'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');
    $this->tenant->domains()->create(['domain' => $domain]);
    $this->domain = $domain;
    tenancy()->initialize($this->tenant);

    app(EnsureTenantRolesExist::class)->execute($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->owner->assignRole('admin');
});

it('closes the workspace and schedules the purge after the grace period', function () {
    Queue::fake();

    $this->actingAs($this->owner);

    Livewire::test(CloseWorkspace::class)
        ->set('confirmSlug', $this->tenant->slug)
        ->set('password', 'password')
        ->call('close')
        ->assertHasNoErrors();

    $row = tenantRow($this->tenant->id);

    expect($row->deleted_at)->not->toBeNull()
        ->and($row->status)->toBe('archived');

    // No immediate purge: the tenants:purge-closed sweep fires after grace.
    Queue::assertNotPushed(PurgeTenantJob::class);
});

it('queues the purge for closed tenants past the grace window', function () {
    Queue::fake();

    DB::table('tenants')->where('id', $this->tenant->id)->update([
        'status' => 'archived',
        'deleted_at' => now()->subDays(31),
    ]);

    $this->artisan('tenants:purge-closed')->assertSuccessful();

    Queue::assertPushed(PurgeTenantJob::class, fn ($job) => $job->tenantId === $this->tenant->id);
});

it('does not purge closed tenants inside the grace window', function () {
    Queue::fake();

    DB::table('tenants')->where('id', $this->tenant->id)->update([
        'status' => 'archived',
        'deleted_at' => now(),
    ]);

    $this->artisan('tenants:purge-closed')->assertSuccessful();

    Queue::assertNotPushed(PurgeTenantJob::class);
});

it('rejects closure with a wrong password', function () {
    Queue::fake();

    $this->actingAs($this->owner);

    Livewire::test(CloseWorkspace::class)
        ->set('confirmSlug', $this->tenant->slug)
        ->set('password', 'wrong-password')
        ->call('close')
        ->assertHasErrors(['password']);

    expect(tenantRow($this->tenant->id)->deleted_at)->toBeNull();

    Queue::assertNotPushed(PurgeTenantJob::class);
});

it('requires a valid MFA code when enabled', function () {
    Queue::fake();

    $google2fa = app(Google2FA::class);
    $secret = $google2fa->generateSecretKey();

    UserMfa::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'method' => 'totp',
        'secret' => $secret,
        'recovery_codes' => ['rc-1', 'rc-2'],
        'enrolled_at' => now(),
    ]);
    $this->owner->update(['mfa_enabled' => true]);

    $this->actingAs($this->owner);

    Livewire::test(CloseWorkspace::class)
        ->set('confirmSlug', $this->tenant->slug)
        ->set('password', 'password')
        ->set('code', '000000')
        ->call('close')
        ->assertHasErrors(['code']);

    expect(tenantRow($this->tenant->id)->deleted_at)->toBeNull();

    Livewire::test(CloseWorkspace::class)
        ->set('confirmSlug', $this->tenant->slug)
        ->set('password', 'password')
        ->set('code', $google2fa->getCurrentOtp($secret))
        ->call('close')
        ->assertHasNoErrors();

    expect(tenantRow($this->tenant->id)->deleted_at)->not->toBeNull();
});

it('forbids closure for non-owners', function () {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $member->assignRole('member');
    $this->actingAs($member);

    Livewire::test(CloseWorkspace::class)
        ->set('confirmSlug', $this->tenant->slug)
        ->set('password', 'password')
        ->call('close')
        ->assertForbidden();

    expect(tenantRow($this->tenant->id)->deleted_at)->toBeNull();
});

it('lets delayed purge jobs run without stancl tenancy', function () {
    $this->tenant->delete();
    tenancy()->end();

    $stub = new class($this->tenant->id) implements TenantAware
    {
        public function __construct(public string $tenantId) {}

        public function tenantId(): string
        {
            return $this->tenantId;
        }
    };

    $ran = false;

    app(RehydrateTenantContext::class)->handle($stub, function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
