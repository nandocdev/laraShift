<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Exceptions;

use Exception;

/**
 * Raised when metered usage is recorded/reported outside a tenant context.
 */
class TenantContextRequiredException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message ?: 'Metered usage requires an active tenant context.');
    }
}
