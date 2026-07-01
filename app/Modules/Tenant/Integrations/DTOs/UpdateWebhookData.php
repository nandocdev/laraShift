<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\DTOs;

use Spatie\LaravelData\Data;

final class UpdateWebhookData extends Data
{
    public function __construct(
        public ?string $url = null,
        public ?string $secret = null,
        public ?array $events = null,
        public ?bool $is_active = null,
        public ?int $max_retries = null,
        public ?int $timeout_seconds = null,
    ) {}
}
