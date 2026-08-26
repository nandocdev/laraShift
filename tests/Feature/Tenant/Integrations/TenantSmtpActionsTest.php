<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\TenantSmtpConfigured;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Experience\Application\Actions\GetTenantSmtpSettings;
use App\Modules\Tenant\Experience\Application\Actions\MarkTenantSmtpVerified;
use App\Modules\Tenant\Experience\Application\Actions\PersistTenantSmtpSettings;
use App\Modules\Tenant\Experience\Application\DTO\SmtpConfigData;
use App\Modules\Tenant\Integrations\Application\Actions\UpdateTenantSmtp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'smtp-'.substr($id, 0, 8),
        'name' => 'SMTP Tenant',
        'email' => 'smtp-'.substr($id, 0, 8).'@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);

    $this->admin = User::factory()->create(['tenant_id' => tenant('id')]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

function smtpConfig(): SmtpConfigData
{
    return new SmtpConfigData(
        host: 'smtp.example.com',
        port: 587,
        user: 'mailer',
        password: 'secret-password',
        fromEmail: 'noreply@example.com',
        fromName: 'Example',
    );
}

it('returns null when smtp is not configured', function () {
    expect(app(GetTenantSmtpSettings::class)->execute())->toBeNull();
});

it('persists settings and resets verification flag', function () {
    app(PersistTenantSmtpSettings::class)->execute(smtpConfig());

    $settings = app(GetTenantSmtpSettings::class)->execute();

    expect($settings)->not->toBeNull()
        ->host->toBe('smtp.example.com')
        ->port->toBe(587)
        ->plainPassword->toBe('secret-password')
        ->verified->toBeFalse();
});

it('marks smtp as verified after successful test', function () {
    app(PersistTenantSmtpSettings::class)->execute(smtpConfig());
    app(MarkTenantSmtpVerified::class)->execute();

    expect(app(GetTenantSmtpSettings::class)->execute()?->verified)->toBeTrue();
});

it('denies persistence to users without manage settings permission', function () {
    $viewer = User::factory()->create(['tenant_id' => tenant('id')]);
    $this->actingAs($viewer);

    app(PersistTenantSmtpSettings::class)->execute(smtpConfig());
})->throws(AuthorizationException::class);

it('fires the integration event when orchestrator runs', function () {
    Event::fake([TenantSmtpConfigured::class]);

    app(UpdateTenantSmtp::class)->execute(
        smtpConfig()
    );

    Event::assertDispatched(
        TenantSmtpConfigured::class,
        fn ($event) => $event->tenantId === tenant('id') && $event->fromEmail === 'noreply@example.com'
    );
});
