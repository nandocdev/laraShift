<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Domain\Exceptions;

use Exception;

class MissingTenantContextException extends Exception
{
    public function __construct(
        public readonly string $jobClass,
        string $message = '',
        int $code = 500
    ) {
        $defaultMessage = __(
            'Job :class accede a datos tenant-aware sin implementar TenantAware. Implementa el contrato TenantAware y usa el middleware RehydrateTenantContext.',
            ['class' => $jobClass]
        );
        parent::__construct($message ?: $defaultMessage, $code);
    }
}
