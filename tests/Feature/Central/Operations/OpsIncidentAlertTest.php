<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Operations\Application\Actions\DetectCriticalIncidents;
use App\Modules\Central\Operations\Infrastructure\Notifications\OpsIncidentNotification;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Notification::fake();
});

function opsAdmin(string $email): CentralUser
{
    return CentralUser::factory()->create(['email' => $email, 'is_global_admin' => true]);
}

function opsTenant(string $slug, string $status): Tenant
{
    return Tenant::create([
        'id' => Str::uuid()->toString(), 'slug' => $slug, 'name' => Str::headline($slug),
        'email' => $slug.'@test.com', 'status' => $status,
    ]);
}

it('mails global admins about new critical incidents only once per day', function () {
    $admin = opsAdmin('ops-admin@test.com');
    opsTenant('ops-failed', 'failed');

    $this->artisan('operations:alert-incidents')->assertSuccessful();

    Notification::assertSentTo($admin, OpsIncidentNotification::class);

    Notification::fake();
    $this->artisan('operations:alert-incidents')->assertSuccessful();

    Notification::assertNothingSent();
});

it('stays silent without critical incidents', function () {
    opsAdmin('ops-quiet@test.com');
    opsTenant('ops-ok', 'active');

    $this->artisan('operations:alert-incidents')->assertSuccessful();

    Notification::assertNothingSent();
});

it('skips mailing when no global admins exist', function () {
    opsTenant('ops-noadmin', 'failed');

    $this->artisan('operations:alert-incidents')->assertSuccessful();

    Notification::assertNothingSent();
});

it('shares one critical definition between the command and the monitor', function () {
    opsAdmin('ops-shared@test.com');
    opsTenant('ops-both', 'quarantine');

    $incidents = app(DetectCriticalIncidents::class)->execute();

    expect(collect($incidents)->pluck('id')->all())->toContain('tenants-quarantine');
});
