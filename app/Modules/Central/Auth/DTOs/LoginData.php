<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\DTOs;

use Spatie\LaravelData\Data;

final class LoginData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
    ) {}
}
