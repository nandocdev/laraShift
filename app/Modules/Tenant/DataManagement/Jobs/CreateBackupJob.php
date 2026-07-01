<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Jobs;

use App\Modules\Central\Billing\Services\BillingExportService;
use App\Modules\Tenant\DataManagement\Models\DataBackup;
use App\Modules\Tenant\Identity\Services\IdentityExportService;
use App\Modules\Tenant\Settings\Services\SettingsExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $backupId,
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $backup = DataBackup::find($this->backupId);

            if (! $backup) {
                return;
            }

            $exportables = [
                new IdentityExportService,
                new SettingsExportService,
                new BillingExportService,
            ];

            $data = [];
            foreach ($exportables as $exportable) {
                $data = array_merge($data, $exportable->getExportData());
            }

            $content = json_encode($data);
            $fileName = "backups/tenant_{$this->tenantId}_".Str::random(8).'.json';

            Storage::disk('private')->put($fileName, $content);

            $size = Storage::disk('private')->size($fileName);

            $backup->update([
                'file_path' => $fileName,
                'size_bytes' => $size,
                'status' => 'completed',
            ]);

            Log::info('Backup created', [
                'tenant_id' => $this->tenantId,
                'size_bytes' => $size,
            ]);
        } catch (\Throwable $e) {
            DataBackup::where('id', $this->backupId)->update([
                'status' => 'failed',
            ]);

            Log::error('Backup failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            tenancy()->end();
        }
    }
}
