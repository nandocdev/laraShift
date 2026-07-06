<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Platform\Events\TenantUserRevoked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('automatically records an audit log when an identity event is fired', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'event-test',
        'name' => 'Event Test',
        'email' => 'events@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($tenant);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Revoked User',
        'email' => 'revoked@test.com',
        'password' => 'password',
    ]);

    // Dispatch event manually
    event(new TenantUserRevoked((string) $user->id, (string) $user->tenant_id, 'admin-uuid'));

    // Check Audit Log
    expect(AuditLog::count())->toBe(1);
    
    $log = AuditLog::first();
    expect($log->action->value)->toBe('user.revoked');
    expect($log->resource)->toBe('users');
    expect($log->resource_id)->toBe($user->id);
    expect($log->metadata['revoked_by'])->toBe('admin-uuid');
});
