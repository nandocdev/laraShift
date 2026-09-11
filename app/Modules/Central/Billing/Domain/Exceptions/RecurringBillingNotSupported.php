<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Exceptions;

use LogicException;

final class RecurringBillingNotSupported extends LogicException {}
