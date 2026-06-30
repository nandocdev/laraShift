<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Exceptions;

class CheckoutFailedException extends \RuntimeException
{
    public static function fromGateway(string $message): self
    {
        return new self("Checkout failed: {$message}");
    }
}
