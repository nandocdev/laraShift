<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\DTOs;

use Spatie\LaravelData\Data;

final class UpsertNotificationTemplateData extends Data
{
    public function __construct(
        public string $key,
        public string $channel = 'email',
        public ?string $subject = null,
        public ?string $body = null,
        public bool $is_active = true,
    ) {}
}
