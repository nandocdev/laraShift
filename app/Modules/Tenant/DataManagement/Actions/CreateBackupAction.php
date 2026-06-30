<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Actions;

use App\Modules\Tenant\DataManagement\Jobs\CreateBackupJob;
use App\Modules\Tenant\DataManagement\Models\DataBackup;
use Illuminate\Support\Str;

final readonly class CreateBackupAction
{
    public function execute(): DataBackup
    {
        $backup = DataBackup::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'file_path' => '',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        CreateBackupJob::dispatch(
            tenantId: tenant('id'),
            backupId: $backup->id,
        );

        return $backup;
    }
}
