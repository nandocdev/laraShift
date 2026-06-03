<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\DTOs;

use Spatie\LaravelData\Data;

final class CreateTenantData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $email,
        public string $plan_id,
        public ?string $password = null,
        public ?string $payment_token = null,
    ) {}
}
