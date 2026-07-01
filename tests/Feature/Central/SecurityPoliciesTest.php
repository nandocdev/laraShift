<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Security\Actions\ResolveTenantEncryptionPolicyAction;
use App\Modules\Central\Security\Actions\RotateEncryptionKeyAction;
use App\Modules\Central\Security\Actions\RotateTenantApiKeysAction;
use App\Modules\Central\Security\Livewire\SecurityPolicies;
use App\Modules\Tenant\Identity\Models\ApiKey;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Security Admin',
        'email' => 'sec@admin.com',
        'password' => 'password',
        'is_global_admin' => true,
    ]);

    $this->actingAs($this->admin, 'central');

    $this->plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 2900,
        'price_yearly' => 29000,
        'is_active' => true,
        'features' => [
            'encryption' => ['key_rotation_days' => 30, 'encrypt_at_rest' => true],
            'retention' => ['audit_logs' => 90],
        ],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'sec-tenant',
        'name' => 'Security Tenant',
        'email' => 'sec@tenant.com',
        'plan_id' => 'pro',
    ]);
});

it('resolves encryption policy from plan features', function () {
    $action = app(ResolveTenantEncryptionPolicyAction::class);
    $policy = $action->execute($this->tenant);

    expect($policy['key_rotation_days'])->toBe(30);
    expect($policy['encrypt_at_rest'])->toBeTrue();
});

it('returns defaults when plan has no encryption config', function () {
    $this->plan->update(['features' => []]);

    $action = app(ResolveTenantEncryptionPolicyAction::class);
    $policy = $action->execute($this->tenant);

    expect($policy['key_rotation_days'])->toBe(90);
    expect($policy['encrypt_at_rest'])->toBeTrue();
});

it('rotates encryption key and deactivates old key', function () {
    $action = app(RotateEncryptionKeyAction::class);

    $firstKey = $action->execute($this->tenant);
    expect($firstKey->is_active)->toBeTrue();

    $secondKey = $action->execute($this->tenant);

    expect($firstKey->fresh()->is_active)->toBeFalse();
    expect($secondKey->is_active)->toBeTrue();
    expect($secondKey->id)->not->toBe($firstKey->id);
});

it('stores encrypted key securely', function () {
    $action = app(RotateEncryptionKeyAction::class);
    $key = $action->execute($this->tenant);

    expect($key->encrypted_key)->not->toBeEmpty();
    expect($key->encrypted_key)->toStartWith('eyJ'); // encrypted values start with base64-encoded JSON

    $decrypted = decrypt($key->encrypted_key);
    expect(strlen($decrypted))->toBe(64); // 32 bytes = 64 hex chars
});

it('rotates expired API keys for a tenant', function () {
    tenancy()->initialize($this->tenant);

    $user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'API User',
        'email' => 'api@test.com',
        'password' => 'password',
    ]);

    $oldKey = ApiKey::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'name' => 'Old Key',
        'key_hash' => 'hash_old',
        'scopes' => ['read'],
        'created_at' => now()->subDays(100),
    ]);
    $oldKey->created_at = now()->subDays(100);
    $oldKey->save();

    $newKey = ApiKey::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'name' => 'Recent Key',
        'key_hash' => 'hash_new',
        'scopes' => ['read'],
        'created_at' => now()->subDays(10),
    ]);

    tenancy()->end();

    $action = app(RotateTenantApiKeysAction::class);
    $rotated = $action->execute($this->tenant->id);

    expect($rotated)->toBe(1);

    tenancy()->initialize($this->tenant);
    expect($oldKey->fresh()->revoked_at)->not->toBeNull();
    expect($newKey->fresh()->revoked_at)->toBeNull();
    tenancy()->end();
});

it('registers security policies livewire component', function () {
    Livewire::test(SecurityPolicies::class)
        ->assertStatus(200);
});
