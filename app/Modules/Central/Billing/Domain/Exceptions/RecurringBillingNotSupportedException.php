<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Exceptions;

use Exception;

/**
 * Raised when a gateway manages recurrence on its own side and does not
 * support engine-managed recurring charges (see ClaveGateway).
 */
class RecurringBillingNotSupportedException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message ?: 'This gateway manages recurrence on its own; engine-managed recurring charges are not supported.');
    }
}
