<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Actions;

use App\Modules\Tenant\Compliance\Application\Jobs\ExportTenantDataJob;

final readonly class ExportTenantData
{
    public function execute(string $userId): void
    {
        ExportTenantDataJob::dispatch(tenant('id'), $userId);
    }
}
