<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Services;

use App\Modules\Shared\Contracts\Exportable;
use App\Modules\Tenant\Settings\Models\TenantSetting;

class SettingsExportService implements Exportable
{
    public function getExportData(): array
    {
        return [
            'settings' => TenantSetting::where('tenant_id', tenant('id'))->first()?->toArray() ?? [],
        ];
    }
}
