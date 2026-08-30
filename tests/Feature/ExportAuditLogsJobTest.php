<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Application\Jobs\ExportAuditLogsJob;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use App\Modules\Tenant\Compliance\Infrastructure\Notifications\AuditLogExportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();
});

it('resolves user via container contract and exports logs correctly', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'export-test',
        'name' => 'Export Test',
        'email' => 'export@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($tenant);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Auditor User',
        'email' => 'auditor@test.com',
        'password' => 'password',
    ]);

    AuditLog::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'action' => AuditAction::AUTH_LOGIN,
        'resource' => 'Auth',
        'resource_id' => $user->id,
        'ip' => '127.0.0.1',
        'metadata' => ['browser' => 'Chrome'],
    ]);

    $job = new ExportAuditLogsJob(
        tenantId: $tenant->id,
        userId: (string) $user->id,
        dateFrom: now()->subDays(5)->toDateString(),
        dateTo: now()->toDateString()
    );

    app()->call([$job, 'handle']);

    Notification::assertSentTo($user, AuditLogExportNotification::class);
    expect(Storage::disk('private')->allFiles('exports/audit'))->toHaveCount(1);
});

it('aborts export if date range exceeds 90 days', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'export-test-2',
        'name' => 'Export Test 2',
        'email' => 'export2@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($tenant);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Auditor User',
        'email' => 'auditor2@test.com',
        'password' => 'password',
    ]);

    $job = new ExportAuditLogsJob(
        tenantId: $tenant->id,
        userId: (string) $user->id,
        dateFrom: now()->subDays(95)->toDateString(),
        dateTo: now()->toDateString()
    );

    app()->call([$job, 'handle']);

    Notification::assertNothingSent();
    expect(Storage::disk('private')->allFiles('exports/audit'))->toHaveCount(0);
});

it('aborts export if dateFrom is after dateTo', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'export-test-3',
        'name' => 'Export Test 3',
        'email' => 'export3@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($tenant);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Auditor User',
        'email' => 'auditor3@test.com',
        'password' => 'password',
    ]);

    $job = new ExportAuditLogsJob(
        tenantId: $tenant->id,
        userId: (string) $user->id,
        dateFrom: now()->toDateString(),
        dateTo: now()->subDays(5)->toDateString()
    );

    app()->call([$job, 'handle']);

    Notification::assertNothingSent();
    expect(Storage::disk('private')->allFiles('exports/audit'))->toHaveCount(0);
});
