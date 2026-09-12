<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Provisioning\Actions\PurgeTenantDataAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function purgeSeed(string $suffix): array
{
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(), 'slug' => "purge-{$suffix}", 'name' => "Purge {$suffix}",
        'email' => "purge-{$suffix}@test.com", 'status' => 'active',
    ]);
    $id = $tenant->id;
    $u = fn (string $prefix) => "{$prefix}-{$suffix}-".Str::random(6);

    $userId = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $userId, 'tenant_id' => $id, 'name' => 'Purge User',
        'email' => "purge-user-{$suffix}@test.com", 'password' => 'secret',
    ]);

    $operator = CentralUser::create([
        'name' => 'Op', 'email' => "op-{$suffix}@test.com", 'password' => 'secret',
    ]);

    $roleId = (string) Str::uuid();
    DB::table('roles')->insert([
        'id' => $roleId, 'tenant_id' => $id, 'name' => 'member', 'guard_name' => 'tenant',
    ]);

    $broadcastId = (string) Str::uuid();
    DB::table('broadcasts')->insert([
        'id' => $broadcastId, 'created_by' => $operator->id, 'title' => 'T', 'body' => 'B',
        'filter_type' => 'all', 'channels' => json_encode(['banner']),
    ]);

    $landingId = (string) Str::uuid();
    DB::table('landings')->insert(['id' => $landingId, 'tenant_id' => $id, 'slug' => 'home']);

    $payment = Payment::factory()->create(['tenant_id' => $id]);

    $subscription = Subscription::create([
        'tenant_id' => $id, 'status' => SubscriptionStatus::Active, 'gateway' => 'clave',
    ]);

    Invoice::create([
        'tenant_id' => $id, 'amount_cents' => 100, 'currency' => 'USD',
        'status' => 'paid', 'issued_at' => now(), 'paid_at' => now(),
    ]);

    DB::table('tenant_api_keys')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'name' => 'k',
        'key_hash' => hash('sha256', $u('k')), 'scopes' => json_encode(['a']),
    ]);
    DB::table('tenant_settings')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $id]);
    DB::table('tenant_audit_logs')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'action' => 'user.created',
    ]);
    DB::table('tenant_notifications')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'type' => 't', 'data' => '{}',
    ]);
    DB::table('tenant_invitations')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'email' => "inv-{$suffix}@test.com",
        'role_id' => $roleId, 'token_hash' => hash('sha256', $u('inv')), 'expires_at' => now()->addDay(),
    ]);
    DB::table('tenant_sessions')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'user_id' => $userId,
        'session_id' => $u('sess'),
    ]);
    DB::table('tenant_sso_settings')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $id]);
    DB::table('user_mfa')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'user_id' => $userId,
        'secret' => 's', 'recovery_codes' => '[]', 'enrolled_at' => now(),
    ]);
    DB::table('passkeys')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'user_id' => $userId,
        'name' => 'k', 'credential_id' => $u('cred'), 'credential' => '{}',
    ]);
    DB::table('tenant_user_impersonation_tokens')->insert([
        'token' => $u('tok'), 'tenant_id' => $id, 'user_id' => $userId,
        'auth_guard' => 'tenant', 'redirect_url' => '/', 'created_at' => now(),
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $roleId, 'model_type' => User::class, 'model_id' => $userId, 'tenant_id' => $id,
    ]);

    $permId = (string) Str::uuid();
    DB::table('permissions')->insert(['id' => $permId, 'name' => "perm-{$suffix}", 'guard_name' => 'tenant']);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permId, 'model_type' => User::class, 'model_id' => $userId, 'tenant_id' => $id,
    ]);

    DB::table('landing_versions')->insert([
        'id' => (string) Str::uuid(), 'landing_id' => $landingId, 'tenant_id' => $id,
        'blocks_snapshot' => '[]', 'theme_snapshot' => '{}',
    ]);
    DB::table('broadcast_tenant')->insert(['broadcast_id' => $broadcastId, 'tenant_id' => $id]);
    DB::table('broadcast_dismissals')->insert([
        'id' => (string) Str::uuid(), 'broadcast_id' => $broadcastId, 'tenant_id' => $id,
        'user_id' => $userId, 'dismissed_at' => now(),
    ]);
    DB::table('support_notes')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'author_id' => $operator->id, 'content' => 'n',
    ]);
    DB::table('support_sessions')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'operator_id' => $operator->id,
        'reason' => 'r', 'token' => $u('sstok'), 'started_at' => now(), 'expires_at' => now()->addHour(),
    ]);
    DB::table('provisioning_logs')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'step' => 'db_schema', 'executed_at' => now(),
    ]);
    DB::table('domains')->insert(['domain' => "purge-{$suffix}.test", 'tenant_id' => $id]);
    DB::table('payment_attempts')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'payment_id' => $payment->id,
        'slug' => $u('att'), 'status' => 'pending',
    ]);
    DB::table('payment_webhooks')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id, 'gateway' => 'clave',
        'gateway_reference' => $u('wh'), 'status' => 'pending',
    ]);
    DB::table('payment_references')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $id,
        'external_reference' => $u('ref'), 'context' => 'customer',
    ]);

    activity('provisioning')->performedOn($tenant)->log('purge_probe');

    return [$tenant, $userId, $payment, $subscription];
}

it('purges every tenant table without touching other tenants', function () {
    [$tenantA] = purgeSeed('a');
    [$tenantB, $userB] = purgeSeed('b');

    app(PurgeTenantDataAction::class)->execute($tenantA->id);

    $tables = [
        'users', 'tenant_api_keys', 'tenant_settings', 'tenant_audit_logs',
        'tenant_notifications', 'tenant_invitations', 'tenant_sessions',
        'tenant_sso_settings', 'user_mfa', 'passkeys',
        'tenant_user_impersonation_tokens', 'model_has_roles', 'model_has_permissions',
        'roles', 'landings', 'landing_versions', 'broadcast_tenant',
        'broadcast_dismissals', 'support_notes', 'support_sessions',
        'provisioning_logs', 'domains', 'subscriptions', 'payments',
        'payment_attempts', 'payment_webhooks', 'payment_references', 'invoices',
    ];

    foreach ($tables as $table) {
        expect(DB::table($table)->where('tenant_id', $tenantA->id)->count())
            ->toBe(0, "leak in {$table}");
    }

    // Control tenant untouched.
    expect(DB::table('users')->where('tenant_id', $tenantB->id)->count())->toBe(1)
        ->and(DB::table('tenant_audit_logs')->where('tenant_id', $tenantB->id)->count())->toBe(1)
        ->and(DB::table('payments')->where('tenant_id', $tenantB->id)->count())->toBe(1)
        ->and(Tenant::find($tenantB->id))->not->toBeNull()
        ->and(User::where('id', $userB)->exists())->toBeTrue();

    // Central accountability trail survives the tenant purge.
    expect(DB::table('activity_log')->where('description', 'purge_probe')->count())->toBe(2);
});
