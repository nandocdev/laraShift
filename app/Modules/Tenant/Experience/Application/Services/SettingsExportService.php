<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Services;

use App\Modules\Platform\Contracts\Exportable;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;

class SettingsExportService implements Exportable
{
    public function exportToStream($handle): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();
        fwrite($handle, '"settings":'.json_encode($settings ? $settings->toArray() : []));
    }
}
