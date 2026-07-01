<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Exceptions;

class Invalid2FACodeException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid two-factor authentication code.')
    {
        parent::__construct($message);
    }
}
