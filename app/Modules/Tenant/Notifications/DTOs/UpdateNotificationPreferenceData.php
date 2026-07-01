<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\DTOs;

use Spatie\LaravelData\Data;

final class UpdateNotificationPreferenceData extends Data
{
    public function __construct(
        public string $notificationKey,
        public string $channel = 'email',
        public bool $enabled = true,
    ) {}
}
