<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Exceptions;

use InvalidArgumentException;

final class UnsupportedBillingGateway extends InvalidArgumentException {}
