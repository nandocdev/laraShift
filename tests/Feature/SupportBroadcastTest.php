<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\SendBroadcastAction;
use App\Modules\Central\Support\DTOs\BroadcastData;
use App\Modules\Central\Support\Livewire\BroadcastCenter;
use App\Modules\Central\Support\Livewire\GlobalAnnouncements;
use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders active banners for the target tenant', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central');

    $tenant = Tenant::create([
        'id' => '00000000-0000-0000-0000-0000000000ac',
        'slug' => 'acme',
        'name' => 'Acme',
        'email' => 'acme@test.com',
        'status' => 'active',
    ]);

    // Create a broadcast with banner channel
    $action = app(SendBroadcastAction::class);
    $action->execute(new BroadcastData(
        title: 'Platform Maintenance',
        body: 'Scheduled for tonight.',
        filterType: 'all',
        channels: ['banner']
    ));

    tenancy()->initialize($tenant);

    Livewire::test(GlobalAnnouncements::class)
        ->assertSee('Platform Maintenance')
        ->assertSee('Scheduled for tonight.');
});

it('hides dismissed banners', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central');

    $tenant = Tenant::create([
        'id' => '00000000-0000-0000-0000-0000000000ac',
        'slug' => 'acme',
        'name' => 'Acme',
        'email' => 'acme@test.com',
    ]);

    $broadcast = app(SendBroadcastAction::class)->execute(new BroadcastData(
        title: 'Discount!',
        body: 'Upgrade now.',
        filterType: 'all',
        channels: ['banner']
    ));

    tenancy()->initialize($tenant);

    Livewire::test(GlobalAnnouncements::class)
        ->assertSee('Discount!')
        ->call('dismiss', $broadcast->id)
        ->assertDontSee('Discount!');

    expect(DB::table('broadcast_dismissals')->count())->toBe(1);
});

it('targets plan audiences and validates unknown plans', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(), 'name' => 'Admin',
        'email' => 'admin-plan@test.com', 'password' => 'password',
    ]);
    $this->actingAs($admin, 'central');

    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 1900, 'price_yearly' => 19000,
        'currency' => 'USD', 'interval' => 'month', 'features' => [], 'is_active' => true,
    ]);

    $action = app(SendBroadcastAction::class);
    $broadcast = $action->execute(new BroadcastData(
        title: 'Pro news', body: 'Hello pro.',
        filterType: 'plan', filterValue: 'pro', channels: ['banner'],
    ));

    expect($broadcast->recipient_count)->toBe(0);

    Livewire::test(BroadcastCenter::class)
        ->set('title', 'x')
        ->set('body', 'y')
        ->set('filterType', 'plan')
        ->set('filterValue', 'ghost-plan')
        ->call('send')
        ->assertHasErrors(['filterValue']);
});

it('sends selected-tenants broadcasts only to the chosen tenants', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(), 'name' => 'Admin',
        'email' => 'admin-sel@test.com', 'password' => 'password',
    ]);
    $this->actingAs($admin, 'central');

    $chosen = Tenant::create([
        'id' => '00000000-0000-0000-0000-0000000000a1', 'slug' => 'chosen',
        'name' => 'Chosen', 'email' => 'chosen@test.com', 'status' => 'active',
    ]);
    $other = Tenant::create([
        'id' => '00000000-0000-0000-0000-0000000000a2', 'slug' => 'other',
        'name' => 'Other', 'email' => 'other@test.com', 'status' => 'active',
    ]);

    $broadcast = app(SendBroadcastAction::class)->execute(new BroadcastData(
        title: 'Selected', body: 'Only you.',
        filterType: 'selected', channels: ['banner'], tenantIds: [$chosen->id],
    ));

    expect($broadcast->recipient_count)->toBe(1);

    tenancy()->initialize($chosen);
    Livewire::test(GlobalAnnouncements::class)->assertSee('Selected');

    tenancy()->initialize($other);
    Livewire::test(GlobalAnnouncements::class)->assertDontSee('Selected');
});

it('schedules broadcasts and dispatches them when due', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(), 'name' => 'Admin',
        'email' => 'admin-sched@test.com', 'password' => 'password',
    ]);
    $this->actingAs($admin, 'central');

    $broadcast = app(SendBroadcastAction::class)->execute(new BroadcastData(
        title: 'Later', body: 'Not yet.',
        filterType: 'all', channels: ['banner'],
        scheduledAt: now()->addHour()->toDateTimeString(),
    ));

    expect($broadcast->sent_at)->toBeNull()
        ->and($broadcast->is_draft)->toBeFalse();

    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();
    expect($broadcast->fresh()->sent_at)->toBeNull();

    $broadcast->update(['scheduled_at' => now()->subMinute()]);
    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();
    expect($broadcast->fresh()->sent_at)->not->toBeNull();
});

it('saves drafts without sending and publishes them on demand', function () {
    $admin = CentralUser::create([
        'id' => Str::uuid()->toString(), 'name' => 'Admin',
        'email' => 'admin-draft@test.com', 'password' => 'password',
    ]);
    $this->actingAs($admin, 'central');

    Livewire::test(BroadcastCenter::class)
        ->set('title', 'Drafted')
        ->set('body', 'Work in progress.')
        ->set('filterType', 'all')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $draft = Broadcast::where('title', 'Drafted')->firstOrFail();
    expect($draft->is_draft)->toBeTrue()->and($draft->sent_at)->toBeNull();

    Livewire::test(BroadcastCenter::class)
        ->call('publishDraft', $draft->id)
        ->assertHasNoErrors();

    expect($draft->fresh()->is_draft)->toBeFalse()
        ->and($draft->fresh()->sent_at)->not->toBeNull();
});
