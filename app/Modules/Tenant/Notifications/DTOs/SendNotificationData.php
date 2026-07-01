<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\DTOs;

use App\Modules\Tenant\Identity\Models\User;
use Spatie\LaravelData\Data;

final class SendNotificationData extends Data
{
    public function __construct(
        public User $user,
        public string $key,
        public array $payload = [],
        public string $channel = 'in-app',
    ) {}
}
