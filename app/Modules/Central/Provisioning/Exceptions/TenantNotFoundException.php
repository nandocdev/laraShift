<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Exceptions;

final class TenantNotFoundException extends ProvisioningException
{
    public function __construct(string $tenantId)
    {
        parent::__construct("Tenant with ID '{$tenantId}' not found.", 404);
    }
}
