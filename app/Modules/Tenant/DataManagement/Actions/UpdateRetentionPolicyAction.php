<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Actions;

use App\Modules\Tenant\DataManagement\DTOs\RetentionPolicyData;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRetentionPolicyAction
{
    public function execute(RetentionPolicyData $data): void
    {
        $tenantId = tenant('id');

        $raw = DB::table('tenants')->where('id', $tenantId)->value('data');
        $currentData = $raw ? (array) json_decode($raw, true) : [];

        $currentData['retention'] = [
            'audit_logs' => $data->audit_logs,
            'notifications' => $data->notifications,
            'activity_log' => $data->activity_log,
            'exports' => $data->exports,
            'backups' => $data->backups,
        ];

        DB::table('tenants')->where('id', $tenantId)->update([
            'data' => json_encode($currentData),
        ]);
    }
}
