<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Exceptions;

use RuntimeException;

final class WebhookVerificationFailed extends RuntimeException {}
