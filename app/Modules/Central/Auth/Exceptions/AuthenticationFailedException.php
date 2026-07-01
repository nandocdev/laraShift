<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Exceptions;

class AuthenticationFailedException extends \RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid email or password.');
    }

    public static function accountLocked(\DateTimeInterface $lockedUntil): self
    {
        return new self("Account is locked until {$lockedUntil->format('Y-m-d H:i:s')}.");
    }
}
