<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\SendBroadcastAction;
use App\Modules\Central\Support\DTOs\BroadcastData;
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
        'plan_id' => 'pro',
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
        'plan_id' => 'pro',
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
