<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\DTOs;

use Spatie\LaravelData\Data;

final class AddTicketMessageData extends Data
{
    public function __construct(
        public string $content,
        public bool $isInternal = true,
    ) {}
}
