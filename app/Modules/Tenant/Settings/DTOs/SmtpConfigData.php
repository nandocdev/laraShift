<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SmtpConfigData extends Data
{
    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        public ?string $password,
        public string $fromEmail,
        public string $fromName,
    ) {}
}
