<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Application\Actions\RecordTenantSession;
use App\Modules\Tenant\Access\Application\Actions\RevokeOtherTenantSessions;
use App\Modules\Tenant\Access\Domain\Models\SsoSetting;
use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Interface\Http\Middleware\ValidateTenantSession;
use App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $id = (string) Str::uuid();
    $this->tenant = Tenant::create([
        'id' => $id,
        'slug' => 'iam-'.substr($id, 0, 8),
        'name' => 'IAM Tenant',
        'email' => 'iam-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);
    $this->domain = $this->tenant->slug.'.'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');
    $this->tenant->domains()->create(['domain' => $this->domain]);
    tenancy()->initialize($this->tenant);
    URL::forceRootUrl('http://'.$this->domain);

    app(EnsureTenantRolesExist::class)->execute($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->admin->assignRole('admin');

    $this->viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->viewer->assignRole('member');
});

it('forbids team role updates for members without team:manage', function () {
    $this->actingAs($this->viewer);

    Livewire::test(TeamManagement::class)
        ->call('updateRole')
        ->assertForbidden();
});

it('forbids team invitations for members without team:manage', function () {
    $this->actingAs($this->viewer);

    Livewire::test(TeamManagement::class)
        ->call('invite')
        ->assertForbidden();
});

it('blocks the team page for members via route middleware', function () {
    $this->actingAs($this->viewer);

    $this->get(route('tenant.team.index'))->assertForbidden();
});

it('isolates sso settings per tenant', function () {
    $otherId = (string) Str::uuid();
    $other = Tenant::create([
        'id' => $otherId,
        'slug' => 'iam-other-'.substr($otherId, 0, 8),
        'name' => 'Other Tenant',
        'email' => 'other-'.substr($otherId, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);

    SsoSetting::create([
        'tenant_id' => $other->id,
        'idp_entity_id' => 'https://idp.example.com/metadata',
        'idp_sso_url' => 'https://idp.example.com/sso',
        'idp_x509_cert' => 'cert',
    ]);

    expect(SsoSetting::first())->toBeNull();

    tenancy()->initialize($other);

    expect(SsoSetting::first()?->tenant_id)->toBe($other->id);
});

it('blocks password login for sso-enforced domains', function () {
    $this->tenant->run(function () {
        SsoSetting::create([
            'tenant_id' => $this->tenant->id,
            'idp_entity_id' => 'https://idp.example.com/metadata',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_x509_cert' => 'cert',
            'enforced_domains' => ['example.com'],
            'is_forced' => true,
            'is_tested' => true,
        ]);

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'forced@example.com',
            'status' => 'active',
        ]);
    });

    $this->post(route('login.store'), [
        'email' => 'forced@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('web');
});

it('revokes other sessions but keeps the current one', function () {
    $keep = Str::random(40);
    Session::setId($keep);

    $current = app(RecordTenantSession::class)
        ->execute($this->admin, '127.0.0.1', 'TestAgent');

    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'session_id' => $drop = Str::random(40),
        'ip' => '127.0.0.2',
    ]);

    $revoked = app(RevokeOtherTenantSessions::class)->execute($this->admin);

    expect($revoked)->toBe(1)
        ->and(TenantSession::where('session_id', $drop)->first()->revoked_at)->not->toBeNull()
        ->and($current->fresh()->revoked_at)->toBeNull();
});

it('logs out requests carrying a revoked session id', function () {
    $revokedId = Str::random(40);
    Session::setId($revokedId);

    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'session_id' => $revokedId,
        'revoked_at' => now(),
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $this->admin);

    $response = app(ValidateTenantSession::class)->handle($request, fn () => response('next'));

    expect($response->isRedirect(route('login')))->toBeTrue();
});

it('lets active sessions through the session middleware', function () {
    $activeId = Str::random(40);
    Session::setId($activeId);

    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'session_id' => $activeId,
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $this->admin);

    $response = app(ValidateTenantSession::class)->handle($request, fn () => response('next'));

    expect($response->getContent())->toBe('next');
});
