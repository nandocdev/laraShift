<?php

declare(strict_types=1);

use App\Modules\Central\Settings\Domain\Models\CentralSetting;
use App\Modules\Central\Settings\Infrastructure\Services\CentralBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('gets default platform name from config', function () {
    expect(CentralBranding::platformName())->toBe(config('app.name'));
});

test('sets and gets a string setting', function () {
    CentralBranding::set('platform_name', 'MyPlatform');

    expect(CentralBranding::platformName())->toBe('MyPlatform');
});

test('persists setting to database', function () {
    CentralBranding::set('platform_name', 'Persisted');

    $setting = CentralSetting::find('platform_name');
    expect($setting)->not->toBeNull();
    expect($setting->value)->toBe('Persisted');
});

test('gets default primary color', function () {
    expect(CentralBranding::primaryColor())->toBe('#000000');
});

test('sets and gets primary color', function () {
    CentralBranding::set('primary_color', '#ff0000');

    expect(CentralBranding::primaryColor())->toBe('#ff0000');
});

test('logo url returns null when not set', function () {
    expect(CentralBranding::logoUrl())->toBeNull();
});

test('sets and gets logo url', function () {
    CentralBranding::set('logo_url', 'https://example.com/logo.png');

    expect(CentralBranding::logoUrl())->toBe('https://example.com/logo.png');
});

test('get returns default for unknown key', function () {
    expect(CentralBranding::get('nonexistent', 'fallback'))->toBe('fallback');
});

test('caches settings after first read', function () {
    CentralBranding::set('platform_name', 'Cached');

    CentralBranding::platformName();

    CentralSetting::where('key', 'platform_name')->update(['value' => 'Changed']);

    expect(CentralBranding::platformName())->toBe('Cached');
});
