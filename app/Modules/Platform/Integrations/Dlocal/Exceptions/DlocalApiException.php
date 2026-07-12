<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Exceptions;

use RuntimeException;
use Throwable;

final class DlocalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $dlocalCode = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
