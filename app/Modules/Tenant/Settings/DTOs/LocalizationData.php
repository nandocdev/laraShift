<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\DTOs;

use Spatie\LaravelData\Data;

final class LocalizationData extends Data
{
    public function __construct(
        public string $timezone,
        public string $locale,
        public string $currency,
    ) {}
}
