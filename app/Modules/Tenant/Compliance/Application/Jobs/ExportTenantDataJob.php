<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Jobs;

use App\Modules\Central\Billing\Application\Services\BillingExportService;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Application\Services\IdentityExportService;
use App\Modules\Tenant\Compliance\Infrastructure\Notifications\TenantDataExportNotification;
use App\Modules\Tenant\Experience\Application\Services\SettingsExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportTenantDataJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $userId
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $exportables = [
            new IdentityExportService,
            new SettingsExportService,
            new BillingExportService,
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'tenant_export');
        $handle = fopen($tmpPath, 'w');
        fwrite($handle, '{');
        $first = true;
        foreach ($exportables as $exportable) {
            if (! $first) {
                fwrite($handle, ',');
            }
            $exportable->exportToStream($handle);
            $first = false;
        }
        fwrite($handle, '}');
        fclose($handle);

        $fileName = 'exports/tenant_data_'.$this->tenantId.'_'.Str::random(8).'.json';
        Storage::disk('private')->putFileAs('', new File($tmpPath), $fileName);
        unlink($tmpPath);

        $user->notify(new TenantDataExportNotification($fileName));
    }
}
