<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\Actions;

use App\Modules\Platform\Events\TenantSmtpConfigured;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use App\Modules\Tenant\Experience\Application\Actions\PersistTenantSmtpSettings;
use App\Modules\Tenant\Experience\Application\DTO\SmtpConfigData;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTenantSmtp
{
    /**
     * Orchestrates the SMTP configuration use case: persists via Experience,
     * then records audit trail and announces the integration event.
     */
    public function execute(SmtpConfigData $data): void
    {
        DB::transaction(function () use ($data): void {
            app(PersistTenantSmtpSettings::class)->execute($data);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::SETTINGS_SMTP_CONFIGURED,
                    resource: 'settings',
                    resourceId: tenant('id'),
                    metadata: ['from_email' => $data->fromEmail]
                )
            );

            event(new TenantSmtpConfigured(tenant('id'), $data->fromEmail));
        });
    }
}
