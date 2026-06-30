<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Actions;

use App\Modules\Tenant\DataManagement\DTOs\RetentionPolicyData;
use Illuminate\Support\Facades\DB;

final readonly class GetRetentionPolicyAction
{
    public function execute(): RetentionPolicyData
    {
        $raw = DB::table('tenants')
            ->where('id', tenant('id'))
            ->value('data');

        $data = $raw ? (array) json_decode($raw, true) : [];
        $retention = $data['retention'] ?? [];

        return new RetentionPolicyData(
            audit_logs: (int) ($retention['audit_logs'] ?? 365),
            notifications: (int) ($retention['notifications'] ?? 180),
            activity_log: (int) ($retention['activity_log'] ?? 365),
            exports: (int) ($retention['exports'] ?? 30),
            backups: (int) ($retention['backups'] ?? 7),
        );
    }
}
