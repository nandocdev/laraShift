<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Security\Actions\RotateEncryptionKeyAction;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Go-Live Smoke Tests.
 *
 * These tests verify critical platform functionality end-to-end.
 * Run before every production deployment.
 */
beforeEach(function () {
    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Smoke Plan',
        'slug' => 'smoke-plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'is_active' => true,
        'features' => [
            'quotas' => ['staff' => 10, 'bookings' => 100, 'invitations' => 20, 'api_keys' => 5],
            'audit_retention_days' => 90,
        ],
    ]);

    $this->tenantA = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'smoke-a',
        'name' => 'Smoke Tenant A',
        'email' => 'a@smoke.com',
        'plan_id' => 'smoke-plan',
    ]);

    $this->tenantB = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'smoke-b',
        'name' => 'Smoke Tenant B',
        'email' => 'b@smoke.com',
        'plan_id' => 'smoke-plan',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('validates tenant isolation: tenant A cannot access tenant B data', function () {
    tenancy()->initialize($this->tenantA);

    $userA = User::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'User A',
        'email' => 'usera@a.com',
        'password' => 'password',
    ]);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantA->id,
        'user_id' => $userA->id,
        'action' => 'auth.login',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    $logs = AuditLog::where('user_id', $userA->id)->get();
    expect($logs)->toBeEmpty();
});

it('validates quota enforcement: rejects over-limit increments', function () {
    tenancy()->initialize($this->tenantA);

    $manager = app(QuotaManager::class);

    $allowed = $manager->increment($this->tenantA, 'staff', 10);
    expect($allowed)->toBeTrue();

    $overLimit = $manager->increment($this->tenantA, 'staff', 1);
    expect($overLimit)->toBeFalse();
});

it('validates Redis counter functionality', function () {
    tenancy()->initialize($this->tenantA);

    $manager = app(QuotaManager::class);

    $manager->forceIncrement($this->tenantA, 'staff', 5);

    $usage = $manager->getCurrentUsage($this->tenantA, 'staff');
    expect($usage)->toBe(5);
});

it('validates plan feature resolution', function () {
    tenancy()->initialize($this->tenantA);

    expect($this->tenantA->hasFeature('nonexistent'))->toBeFalse();

    $feature = Feature::create([
        'id' => Str::uuid()->toString(),
        'key' => 'smoke.feature',
        'name' => 'Smoke Feature',
    ]);

    $this->tenantA->plan->catalogFeatures()->attach($feature->id);

    expect($this->tenantA->fresh()->hasFeature('smoke.feature'))->toBeTrue();
});

it('validates tenant provisioning status flow', function () {
    expect($this->tenantA->status)->toBeNull();

    $this->tenantA->update([
        'status' => 'active',
        'provisioning_status' => 'completed',
    ]);

    expect($this->tenantA->fresh()->status)->toBe('active');
    expect($this->tenantA->fresh()->provisioning_status)->toBe('completed');
});

it('validates security headers middleware', function () {
    tenancy()->initialize($this->tenantA);

    $admin = CentralUser::create([
        'name' => 'Smoke Admin',
        'email' => 'smoke@admin.com',
        'password' => 'password',
    ]);

    $response = $this->actingAs($admin, 'central')
        ->get(route('central.analytics.dashboard'));

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('validates the health endpoint returns a response', function () {
    $response = $this->get('/central/health');
    expect(in_array($response->status(), [200, 302]))->toBeTrue();
});

it('validates encryption key rotation', function () {
    $action = app(RotateEncryptionKeyAction::class);

    $key1 = $action->execute($this->tenantA);
    $key2 = $action->execute($this->tenantA);

    expect($key1->fresh()->is_active)->toBeFalse();
    expect($key2->is_active)->toBeTrue();
    expect($key2->id)->not->toBe($key1->id);
});
