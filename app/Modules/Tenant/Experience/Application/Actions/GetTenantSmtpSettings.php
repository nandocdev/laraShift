<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Actions;

use App\Modules\Tenant\Experience\Application\DTO\TenantSmtpData;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;

final readonly class GetTenantSmtpSettings
{
    /**
     * Returns the current tenant SMTP configuration, or null when unset.
     * The stored password is returned decrypted for internal mailer usage.
     */
    public function execute(): ?TenantSmtpData
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();

        if ($settings === null || $settings->smtp_host === null) {
            return null;
        }

        return new TenantSmtpData(
            host: (string) $settings->smtp_host,
            port: (int) $settings->smtp_port,
            user: (string) $settings->smtp_user,
            plainPassword: $settings->smtp_password,
            fromEmail: (string) ($settings->smtp_from_email ?? ''),
            fromName: (string) ($settings->smtp_from_name ?? ''),
            verified: (bool) $settings->smtp_verified,
        );
    }
}
