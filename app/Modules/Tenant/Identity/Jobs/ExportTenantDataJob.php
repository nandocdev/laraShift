<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Jobs;

use App\Modules\Shared\Contracts\Exportable;
use App\Modules\Tenant\Identity\Notifications\TenantDataExportNotification;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportTenantDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $userId
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $user = User::find($this->userId);
            
            if (! $user) return;

            // Collect data from various modules that implement Exportable
            // Each service will now be scoped to the initialized tenant.
            $exportables = [
                new \App\Modules\Tenant\Identity\Services\IdentityExportService(),
                new \App\Modules\Tenant\Settings\Services\SettingsExportService(),
                new \App\Modules\Central\Billing\Services\BillingExportService(),
            ];

            $data = [];
            foreach ($exportables as $exportable) {
                $data = array_merge($data, $exportable->getExportData());
            }

            $fileName = "exports/tenant_data_{$this->tenantId}_" . Str::random(8) . ".json";
            Storage::disk('private')->put($fileName, json_encode($data));

            // Notify User
            $user->notify(new TenantDataExportNotification($fileName));
        } finally {
            tenancy()->end();
        }
    }
}
