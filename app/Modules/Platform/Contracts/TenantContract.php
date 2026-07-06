<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface TenantContract
{
    public function getId(): string|int;
    public function getName(): string;
    public function getDomain(): string;
    public function getQuotaLimit(string $metric): int;
    public function notify($instance);
}
