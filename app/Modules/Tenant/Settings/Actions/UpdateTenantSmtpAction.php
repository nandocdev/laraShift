<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Actions;

use App\Modules\Shared\Events\TenantSmtpConfigured;
use App\Modules\Tenant\Settings\DTOs\SmtpConfigData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTenantSmtpAction
{
    /**
     * Updates tenant SMTP settings.
     */
    public function execute(SmtpConfigData $data): TenantSetting
    {
        return DB::transaction(function () use ($data) {
            $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
            
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

            app(\App\Modules\Tenant\Audit\Actions\RecordAuditLogAction::class)->execute(
                new \App\Modules\Tenant\Audit\DTOs\AuditLogData(
                    action: \App\Modules\Tenant\Audit\Enums\AuditAction::SETTINGS_SMTP_CONFIGURED,
                    resource: 'settings',
                    resourceId: $settings->id,
                    metadata: ['from_email' => $data->fromEmail]
                )
            );

            event(new TenantSmtpConfigured(tenant('id'), $data->fromEmail));

            return $settings;
        });
    }
}
