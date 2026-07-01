<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Services;

use App\Modules\Tenant\Settings\DTOs\SmtpConfigData;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport;

class TenantMailerService
{
    /**
     * Executes a callback with a temporarily configured mailer.
     * This avoids side-effects on other requests in the same process.
     */
    public function withConfig(SmtpConfigData $config, callable $callback): mixed
    {
        $originalConfig = Config::get('mail.mailers.smtp');

        // Note: Using Config::set() is generally safe for the current request,
        // but we must be careful in Octane. For tests, we use a custom transport.

        $smtpConfig = [
            'transport' => 'smtp',
            'host' => $config->host,
            'port' => $config->port,
            'encryption' => $config->port === 465 ? 'ssl' : 'tls',
            'username' => $config->user,
            'password' => $config->password,
            'timeout' => 5,
        ];

        Config::set('mail.mailers.tenant_test', $smtpConfig);

        try {
            return $callback(Mail::mailer('tenant_test'));
        } finally {
            Config::set('mail.mailers.tenant_test', null);
        }
    }
}
