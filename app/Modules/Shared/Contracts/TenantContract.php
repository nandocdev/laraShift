<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface TenantContract
{
    public function getId(): string|int;
    public function getName(): string;
    public function getDomain(): string;
}
