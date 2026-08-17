<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Enums;

enum AggregationType: string
{
    /**
     * Values accumulate over the billing period (e.g. bookings, API calls).
     */
    case Sum = 'sum';

    /**
     * High-water mark / gauge: the largest recorded value wins (e.g. active staff).
     */
    case Max = 'max';
}
