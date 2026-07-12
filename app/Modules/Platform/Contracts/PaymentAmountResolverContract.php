<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface PaymentAmountResolverContract
{
    /**
     * Resolves the expected payment amount for a given display/reference ID.
     */
    public function resolveAmount(string $displayId): float;
}
