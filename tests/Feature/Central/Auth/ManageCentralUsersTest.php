<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Actions\LoginCentralUserAction;
use App\Modules\Central\Auth\DTOs\LoginData;
use App\Modules\Central\Auth\Http\Middleware\ValidateCentralSession;
use App\Modules\Central\Auth\Livewire\ManageCentralUsers;
use App\Modules\Central\Auth\Models\CentralSession;
use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function globalAdmin(): CentralUser
{
    return CentralUser::factory()->create(['is_global_admin' => true]);
}

it('redirects guests to central login', function () {
    $this->get(route('central.auth.users'))
        ->assertRedirect(route('central.login'));
});

it('forbids staff without global admin', function () {
    $this->withoutMiddleware(ValidateCentralSession::class);
    $this->actingAs(CentralUser::factory()->create(['is_global_admin' => false]), 'central');

    $this->get(route('central.auth.users'))->assertForbidden();
});

it('renders the admin list for global admins', function () {
    $this->withoutMiddleware(ValidateCentralSession::class);
    $this->actingAs(globalAdmin(), 'central');

    $this->get(route('central.auth.users'))
        ->assertOk()
        ->assertSee('Admin Users')
        ->assertSee('Invite Admin');
});

it('invites an admin with valid data', function () {
    $this->actingAs(globalAdmin(), 'central');

    Livewire::test(ManageCentralUsers::class)
        ->set('name', 'New Admin')
        ->set('email', 'new-admin@test.com')
        ->set('password', 'SecurePass123!')
        ->set('password_confirmation', 'SecurePass123!')
        ->call('invite')
        ->assertHasNoErrors();

    expect(CentralUser::where('email', 'new-admin@test.com')->exists())->toBeTrue();
});

it('toggles roles but never its own', function () {
    $admin = globalAdmin();
    $this->actingAs($admin, 'central');

    $staff = CentralUser::factory()->create(['is_global_admin' => false]);

    Livewire::test(ManageCentralUsers::class)
        ->call('toggleAdmin', $staff->id)
        ->assertHasNoErrors();

    expect($staff->fresh()->is_global_admin)->toBeTrue();

    Livewire::test(ManageCentralUsers::class)
        ->call('toggleAdmin', $admin->id)
        ->assertHasErrors(['role']);

    expect($admin->fresh()->is_global_admin)->toBeTrue();
});

it('disables access and blocks login until re-enabled', function () {
    $admin = globalAdmin();
    $this->actingAs($admin, 'central');

    $staff = CentralUser::factory()->create();

    Livewire::test(ManageCentralUsers::class)
        ->call('disable', $staff->id)
        ->assertHasNoErrors();

    expect($staff->fresh()->locked_until)->not->toBeNull();

    $result = app(LoginCentralUserAction::class)->execute(new LoginData(
        email: $staff->email, password: 'password', remember: false,
    ));
    expect($result)->toBe('failed');

    Livewire::test(ManageCentralUsers::class)
        ->call('enable', $staff->id)
        ->assertHasNoErrors();

    expect($staff->fresh()->locked_until)->toBeNull();
});

it('refuses to disable itself', function () {
    $admin = globalAdmin();
    $this->actingAs($admin, 'central');

    Livewire::test(ManageCentralUsers::class)
        ->call('disable', $admin->id)
        ->assertHasErrors(['role']);

    expect($admin->fresh()->locked_until)->toBeNull();
});

it('revokes sessions of other admins', function () {
    $admin = globalAdmin();
    $this->actingAs($admin, 'central');

    $staff = CentralUser::factory()->create();

    CentralSession::create([
        'id' => Str::uuid()->toString(), 'user_id' => $staff->id,
        'session_id' => 'sess-1', 'ip' => '127.0.0.1',
        'issued_at' => now(), 'expires_at' => now()->addHour(),
    ]);

    Livewire::test(ManageCentralUsers::class)
        ->call('revokeSessions', $staff->id)
        ->assertHasNoErrors();

    expect(CentralSession::where('user_id', $staff->id)->whereNull('revoked_at')->count())->toBe(0);
});

it('has no delete action to preserve audit trail', function () {
    expect(method_exists(ManageCentralUsers::class, 'delete'))->toBeFalse();
});
