<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\DTO;

use Spatie\LaravelData\Data;

final class TenantSmtpData extends Data
{
    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        public ?string $plainPassword,
        public string $fromEmail,
        public string $fromName,
        public bool $verified,
    ) {}
}
