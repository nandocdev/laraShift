<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('saves localization settings for the tenant', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'loc-test',
        'name' => 'Loc Test',
        'email' => 'loc@test.com',
    ]);

    tenancy()->initialize($tenant);

    $settings = TenantSetting::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'timezone' => 'UTC',
    ]);

    $settings->update([
        'timezone' => 'America/New_York',
        'locale' => 'en',
        'currency' => 'EUR',
    ]);

    expect($settings->fresh()->timezone)->toBe('America/New_York');
    expect($settings->fresh()->locale)->toBe('en');
    expect($settings->fresh()->currency)->toBe('EUR');
});

it('encrypts smtp passwords automatically', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'smtp-test',
        'name' => 'SMTP Test',
        'email' => 'smtp@test.com',
    ]);

    tenancy()->initialize($tenant);

    $settings = TenantSetting::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'smtp_password' => 'secret-key',
    ]);

    // Check that it's encrypted in the DB
    $raw = DB::table('tenant_settings')->where('tenant_id', $tenant->id)->first();
    expect($raw->smtp_password)->not->toBe('secret-key');

    // Check that model decrypts it
    expect($settings->fresh()->smtp_password)->toBe('secret-key');
});
