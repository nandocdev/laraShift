<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\DTOs;

use Spatie\LaravelData\Data;

final class UserAcceptanceData extends Data
{
    public function __construct(
        public string $token,
        public string $name,
        public string $password,
    ) {}
}
