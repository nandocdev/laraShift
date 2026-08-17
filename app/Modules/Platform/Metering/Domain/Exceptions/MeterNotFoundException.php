<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Exceptions;

use Exception;

class MeterNotFoundException extends Exception
{
    public function __construct(string $meter)
    {
        parent::__construct("Meter [{$meter}] is not registered. Add it to config/metering.php.");
    }
}
