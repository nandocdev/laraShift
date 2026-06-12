<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Actions;

use App\Modules\Shared\Events\TenantSettingsUpdated;
use App\Modules\Tenant\Settings\DTOs\LocalizationData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTenantLocalizationAction
{
    /**
     * Updates tenant localization settings.
     */
    public function execute(LocalizationData $data): TenantSetting
    {
        // Validation of timezone should have happened in Livewire or DTO,
        // but we double check here for runtime safety.
        if (! in_array($data->timezone, timezone_identifiers_list(), true)) {
            throw new \InvalidArgumentException("Invalid timezone: {$data->timezone}");
        }

        return DB::transaction(function () use ($data) {
            $settings = TenantSetting::updateOrCreate(
                ['tenant_id' => tenant('id')],
                [
                    'timezone' => $data->timezone,
                    'locale' => $data->locale,
                    'currency' => $data->currency,
                ]
            );

            event(new TenantSettingsUpdated(tenant('id'), ['timezone', 'locale', 'currency']));

            return $settings;
        });
    }
}
