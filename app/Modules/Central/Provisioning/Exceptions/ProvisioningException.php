<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Exceptions;

use RuntimeException;

abstract class ProvisioningException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
