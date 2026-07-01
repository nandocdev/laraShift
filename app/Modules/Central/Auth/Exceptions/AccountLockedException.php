<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Exceptions;

class AccountLockedException extends \RuntimeException
{
    public function __construct(
        public readonly \DateTimeInterface $lockedUntil,
        string $message = '',
    ) {
        parent::__construct($message ?: "Account is locked until {$lockedUntil->format('Y-m-d H:i:s')}.");
    }
}
