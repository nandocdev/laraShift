<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\DTOs;

use Spatie\LaravelData\Data;

final class BroadcastData extends Data
{
    public function __construct(
        public string $title,
        public string $body,
        public string $filterType = 'all',
        public ?string $filterValue = null,
        public array $channels = ['email'],
    ) {}
}
