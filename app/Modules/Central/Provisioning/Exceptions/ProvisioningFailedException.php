<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Exceptions;

final class ProvisioningFailedException extends ProvisioningException
{
    public function __construct(string $tenantId, string $step, string $reason)
    {
        parent::__construct("Provisioning failed for tenant {$tenantId} at step '{$step}': {$reason}", 500);
    }
}
