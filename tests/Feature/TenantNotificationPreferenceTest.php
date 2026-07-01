<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Notifications\Actions\UpdateNotificationPreferenceAction;
use App\Modules\Tenant\Notifications\DTOs\UpdateNotificationPreferenceData;
use App\Modules\Tenant\Notifications\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'pref-test',
        'name' => 'Preference Test',
        'email' => 'pref@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Pref User',
        'email' => 'pref@user.com',
        'password' => 'password',
    ]);

    $this->actingAs($this->user);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('creates a notification preference', function () {
    $action = app(UpdateNotificationPreferenceAction::class);

    $data = new UpdateNotificationPreferenceData(
        notificationKey: 'user.invited',
        channel: 'email',
        enabled: true,
    );

    $pref = $action->execute((string) $this->user->id, $data);

    expect($pref)->toBeInstanceOf(UserNotificationPreference::class);
    expect($pref->notification_key)->toBe('user.invited');
    expect($pref->enabled)->toBeTrue();
});

it('updates existing preference', function () {
    $action = app(UpdateNotificationPreferenceAction::class);

    $action->execute((string) $this->user->id, new UpdateNotificationPreferenceData(
        notificationKey: 'user.invited',
        channel: 'email',
        enabled: true,
    ));

    $action->execute((string) $this->user->id, new UpdateNotificationPreferenceData(
        notificationKey: 'user.invited',
        channel: 'email',
        enabled: false,
    ));

    expect(UserNotificationPreference::count())->toBe(1);
    expect(UserNotificationPreference::first()->enabled)->toBeFalse();
});

it('scopes preferences to tenant', function () {
    $otherTenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'other-pref',
        'name' => 'Other',
        'email' => 'other@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->end();

    tenancy()->initialize($otherTenant);

    UserNotificationPreference::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $otherTenant->id,
        'user_id' => (string) Str::uuid(),
        'notification_key' => 'other.key',
        'channel' => 'email',
        'enabled' => true,
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenant);

    expect(UserNotificationPreference::count())->toBe(0);
});
