<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Actions;

use App\Modules\Tenant\Experience\Application\DTO\SmtpConfigData;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class PersistTenantSmtpSettings
{
    /**
     * Persists tenant SMTP settings and resets the verification flag.
     */
    public function execute(SmtpConfigData $data): void
    {
        DB::transaction(function () use ($data): void {
            $settings = TenantSetting::firstOrCreate(['tenant_id' => tenant('id')]);

            Gate::authorize('update', $settings);

            $updateData = [
                'smtp_host' => $data->host,
                'smtp_port' => $data->port,
                'smtp_user' => $data->user,
                'smtp_from_email' => $data->fromEmail,
                'smtp_from_name' => $data->fromName,
                'smtp_verified' => false, // Reset on save
            ];

            if ($data->password) {
                $updateData['smtp_password'] = $data->password;
            }

            $settings->update($updateData);
        });
    }
}
