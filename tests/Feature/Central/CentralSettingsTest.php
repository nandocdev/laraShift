<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Settings\Actions\SaveBrandingAction;
use App\Modules\Central\Settings\Livewire\PlatformBranding;
use App\Modules\Central\Settings\Models\CentralSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Settings Admin',
        'email' => 'settings-admin@test.com',
        'password' => 'password',
        'is_global_admin' => true,
    ]);

    $this->actingAs($this->admin, 'central');

    foreach (['central_setting_platform_name', 'central_setting_primary_color', 'central_setting_logo_url'] as $key) {
        \Illuminate\Support\Facades\Cache::forget($key);
    }
});

test('save branding action stores settings', function () {
    $action = app(SaveBrandingAction::class);

    $action->execute('My SaaS', '#ff0000', 'https://example.com/logo.png');

    expect(CentralSetting::find('platform_name')->value)->toBe('My SaaS');
    expect(CentralSetting::find('primary_color')->value)->toBe('#ff0000');
    expect(CentralSetting::find('logo_url')->value)->toBe('https://example.com/logo.png');
});

test('save branding action updates existing settings', function () {
    CentralSetting::create(['key' => 'platform_name', 'value' => 'Old Name']);

    $action = app(SaveBrandingAction::class);
    $action->execute('New Name', '#000000');

    expect(CentralSetting::find('platform_name')->value)->toBe('New Name');
});

test('central branding get returns cached value', function () {
    CentralSetting::create(['key' => 'platform_name', 'value' => 'Cached SaaS']);

    $name = \App\Modules\Central\Settings\Support\CentralBranding::platformName();

    expect($name)->toBe('Cached SaaS');
});

test('central branding get returns default for missing key', function () {
    $name = \App\Modules\Central\Settings\Support\CentralBranding::platformName();

    expect($name)->toBe(config('app.name', 'LaraShift'));
});

test('central branding cache clears on set', function () {
    CentralSetting::create(['key' => 'primary_color', 'value' => '#000000']);

    $first = \App\Modules\Central\Settings\Support\CentralBranding::primaryColor();
    expect($first)->toBe('#000000');

    \App\Modules\Central\Settings\Support\CentralBranding::set('primary_color', '#ffffff');

    $second = \App\Modules\Central\Settings\Support\CentralBranding::primaryColor();
    expect($second)->toBe('#ffffff');
});

test('platform branding livewire renders and saves', function () {
    Livewire::test(PlatformBranding::class)
        ->assertStatus(200)
        ->set('platformName', 'Test SaaS')
        ->set('primaryColor', '#2563eb')
        ->call('save')
        ->assertStatus(200);

    expect(CentralSetting::find('platform_name')->value)->toBe('Test SaaS');
});
