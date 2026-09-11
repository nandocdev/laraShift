<?php

declare(strict_types=1);

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Domain\Models\UserMfa;
use App\Modules\Tenant\Access\Interface\Livewire\LoginChallenge;
use App\Modules\Tenant\Experience\Application\DTO\SmtpConfigData;
use App\Modules\Tenant\Integrations\Application\Services\TenantMailerService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $id = (string) Str::uuid();
    $this->tenant = Tenant::create([
        'id' => $id,
        'slug' => 'creds-'.substr($id, 0, 8),
        'name' => 'Creds Tenant',
        'email' => 'creds-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);
    $domain = $this->tenant->slug.'.'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');
    $this->tenant->domains()->create(['domain' => $domain]);
    tenancy()->initialize($this->tenant);
    URL::forceRootUrl('http://'.$domain);

    app(EnsureTenantRolesExist::class)->execute($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->user->assignRole('member');
});

it('updates the password and revokes other sessions', function () {
    Session::setId(Str::random(40));

    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Session::getId(),
    ]);
    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Str::random(40),
    ]);

    $this->actingAs($this->user);

    app(UpdateUserPassword::class)->update($this->user, [
        'current_password' => 'password',
        'password' => 'New-Secure-1!',
        'password_confirmation' => 'New-Secure-1!',
    ]);

    expect(Hash::check('New-Secure-1!', $this->user->fresh()->password))->toBeTrue()
        ->and(TenantSession::where('user_id', $this->user->id)->whereNull('revoked_at')->count())->toBe(1);
});

it('revokes every session on forgotten-password reset', function () {
    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Str::random(40),
    ]);
    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Str::random(40),
    ]);

    app(ResetUserPassword::class)->reset($this->user, [
        'password' => 'Reset-Secure-1!',
        'password_confirmation' => 'Reset-Secure-1!',
    ]);

    expect(Hash::check('Reset-Secure-1!', $this->user->fresh()->password))->toBeTrue()
        ->and(TenantSession::where('user_id', $this->user->id)->whereNull('revoked_at')->count())->toBe(0);
});

it('logs in with a recovery code and consumes it', function () {
    UserMfa::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'method' => 'totp',
        'secret' => app(Google2FA::class)->generateSecretKey(),
        'recovery_codes' => ['RECOVERY-ONE', 'RECOVERY-TWO'],
        'enrolled_at' => now(),
    ]);
    $this->user->update(['mfa_enabled' => true]);

    Session::put('login.id', $this->user->id);

    Livewire::test(LoginChallenge::class)
        ->set('code', 'RECOVERY-ONE')
        ->call('verify')
        ->assertRedirect(route('dashboard'));

    expect($this->user->mfa->fresh()->recovery_codes)->toBe(['RECOVERY-TWO']);
});

it('rejects an unknown recovery code', function () {
    UserMfa::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'method' => 'totp',
        'secret' => app(Google2FA::class)->generateSecretKey(),
        'recovery_codes' => ['RECOVERY-ONE'],
        'enrolled_at' => now(),
    ]);
    $this->user->update(['mfa_enabled' => true]);

    Session::put('login.id', $this->user->id);

    Livewire::test(LoginChallenge::class)
        ->set('code', 'WRONG-CODE')
        ->call('verify')
        ->assertHasErrors(['code'])
        ->assertNoRedirect();
});

it('isolates mail transports between tenants', function () {
    $service = app(TenantMailerService::class);

    $make = fn (string $host) => new SmtpConfigData(
        host: $host, port: 587, user: 'user', password: 'pass',
        fromEmail: 'noreply@example.com', fromName: 'Test',
    );

    $transportA = $service->withConfig($make('smtp-a.example'), fn ($mailer) => $mailer->getSymfonyTransport());
    $transportB = $service->withConfig($make('smtp-b.example'), fn ($mailer) => $mailer->getSymfonyTransport());

    expect((string) $transportA)->toContain('smtp-a.example')
        ->and((string) $transportB)->toContain('smtp-b.example');
});

it('blocks smtp settings for members without settings:manage', function () {
    $this->actingAs($this->user)
        ->get(route('tenant.settings.smtp'))
        ->assertForbidden();
});
