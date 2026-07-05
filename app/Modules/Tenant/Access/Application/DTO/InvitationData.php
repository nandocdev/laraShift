<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\DTO;

use Spatie\LaravelData\Data;

final class InvitationData extends Data
{
    public function __construct(
        public string $email,
        public string $roleName,
    ) {}
}
