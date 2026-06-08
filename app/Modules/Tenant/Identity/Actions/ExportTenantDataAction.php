<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Jobs\ExportTenantDataJob;

final readonly class ExportTenantDataAction
{
    public function execute(string $userId): void
    {
        ExportTenantDataJob::dispatch(tenant('id'), $userId);
    }
}
