<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Application\Actions;

use App\Modules\Tenant\Audit\Application\Jobs\ExportTenantDataJob;

final readonly class ExportTenantData
{
    public function execute(string $userId): void
    {
        ExportTenantDataJob::dispatch(tenant('id'), $userId);
    }
}
