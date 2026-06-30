<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\DTOs;

use Spatie\LaravelData\Data;

final class CreateWebhookData extends Data
{
    public function __construct(
        public string $url,
        public string $secret,
        public array $events,
        public bool $is_active = true,
        public int $max_retries = 5,
        public int $timeout_seconds = 5,
    ) {}
}
