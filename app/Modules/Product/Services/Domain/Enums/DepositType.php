<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Domain\Enums;

enum DepositType: string
{
    case None = 'none';
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
