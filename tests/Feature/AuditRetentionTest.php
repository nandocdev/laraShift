<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('prunes audit rows older than the retention window and keeps fresh ones', function () {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(), 'slug' => 'retention', 'name' => 'Retention',
        'email' => 'retention@test.com', 'status' => 'active',
    ]);

    $oldLog = AuditLog::create([
        'tenant_id' => $tenant->id, 'action' => AuditAction::AUTH_LOGIN,
    ]);
    $freshLog = AuditLog::create([
        'tenant_id' => $tenant->id, 'action' => AuditAction::AUTH_LOGOUT,
    ]);
    DB::table('tenant_audit_logs')->where('id', $oldLog->id)->update(['created_at' => now()->subDays(400)]);

    activity('support')->log('old-central-entry');
    activity('support')->log('fresh-central-entry');
    DB::table('activity_log')->where('description', 'old-central-entry')->update(['created_at' => now()->subDays(400)]);

    $this->artisan('compliance:prune-audit-logs')->assertSuccessful();

    expect(AuditLog::find($oldLog->id))->toBeNull()
        ->and(AuditLog::find($freshLog->id))->not->toBeNull()
        ->and(DB::table('activity_log')->where('description', 'old-central-entry')->count())->toBe(0)
        ->and(DB::table('activity_log')->where('description', 'fresh-central-entry')->count())->toBe(1);
});

it('honours a custom retention window via --days', function () {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(), 'slug' => 'retention-opt', 'name' => 'Retention Opt',
        'email' => 'retention-opt@test.com', 'status' => 'active',
    ]);

    $log = AuditLog::create(['tenant_id' => $tenant->id, 'action' => AuditAction::AUTH_LOGIN]);
    DB::table('tenant_audit_logs')->where('id', $log->id)->update(['created_at' => now()->subDays(10)]);

    $this->artisan('compliance:prune-audit-logs', ['--days' => 7])->assertSuccessful();

    expect(AuditLog::find($log->id))->toBeNull();
});

it('rejects retention windows below one day', function () {
    $this->artisan('compliance:prune-audit-logs', ['--days' => 0])->assertFailed();
});
