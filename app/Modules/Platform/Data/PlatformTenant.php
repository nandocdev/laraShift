<?php

declare(strict_types=1);

namespace App\Modules\Platform\Data;

use App\Modules\Platform\Contracts\TenantContract;

class PlatformTenant implements TenantContract
{
    public function __construct(
        private string $id,
        private string $name = 'Unknown',
        private string $domain = ''
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getQuotaLimit(string $metric): int
    {
        return -1;
    }

    public function notify(mixed $notification): void
    {
        // No-op for platform/generic tenant
    }
}
