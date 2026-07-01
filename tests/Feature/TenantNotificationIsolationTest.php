<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Models\Notification;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Notifications\Actions\SendInAppNotificationAction;
use App\Modules\Tenant\Notifications\DTOs\SendNotificationData;
use App\Modules\Tenant\Notifications\Livewire\ManageNotificationTemplates;
use App\Modules\Tenant\Notifications\Livewire\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantA = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'notif-a',
        'name' => 'Tenant A',
        'email' => 'a@test.com',
        'plan_id' => 'free',
    ]);

    $this->tenantB = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'notif-b',
        'name' => 'Tenant B',
        'email' => 'b@test.com',
        'plan_id' => 'free',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('tenant A notifications are not visible to tenant B', function () {
    tenancy()->initialize($this->tenantA);

    $userA = User::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'User A',
        'email' => 'usera@a.com',
        'password' => 'password',
    ]);

    $notif = Notification::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantA->id,
        'notifiable_id' => $userA->id,
        'notifiable_type' => get_class($userA),
        'type' => 'test',
        'data' => ['message' => 'Secret for A'],
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    $retrieved = Notification::where('id', $notif->id)->first();

    expect($retrieved)->toBeNull();
});

it('sends in-app notification to the correct user', function () {
    tenancy()->initialize($this->tenantA);

    $user = User::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Target User',
        'email' => 'target@a.com',
        'password' => 'password',
    ]);

    $action = app(SendInAppNotificationAction::class);

    $data = new SendNotificationData(
        user: $user,
        key: 'test.event',
        payload: ['message' => 'Hello!'],
        channel: 'in-app',
    );

    $notification = $action->execute($data);

    expect($notification->notifiable_id)->toBe($user->id);
    expect($notification->data['message'])->toBe('Hello!');
    expect($notification->data['key'])->toBe('test.event');

    // Verify tenant isolation
    $inDb = Notification::where('id', $notification->id)->first();
    expect($inDb->tenant_id)->toBe($this->tenantA->id);
});

it('creates notification for any user id but enforces tenant scope', function () {
    tenancy()->initialize($this->tenantA);

    $userB = User::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'User B',
        'email' => 'userb@b.com',
        'password' => 'password',
    ]);

    $action = app(SendInAppNotificationAction::class);

    $data = new SendNotificationData(
        user: $userB,
        key: 'cross.tenant',
        payload: ['message' => 'Cross tenant'],
        channel: 'in-app',
    );

    $notification = $action->execute($data);

    // Notification is stored under tenant A context
    expect($notification->tenant_id)->toBe($this->tenantA->id);

    tenancy()->end();

    // Tenant B cannot see it
    tenancy()->initialize($this->tenantB);

    $retrieved = Notification::where('id', $notification->id)->first();
    expect($retrieved)->toBeNull();
});

it('registers livewire components', function () {
    tenancy()->initialize($this->tenantA);

    $user = User::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Route Test',
        'email' => 'route@test.com',
        'password' => 'password',
    ]);

    Livewire::actingAs($user);

    Livewire::test(ManageNotificationTemplates::class)
        ->assertStatus(200);

    Livewire::test(NotificationPreferences::class)
        ->assertStatus(200);
});
