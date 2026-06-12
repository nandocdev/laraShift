<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Tenant\Settings\Actions\UpdateTenantLocalizationAction;
use App\Modules\Tenant\Settings\DTOs\LocalizationData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LocalizationSettings extends Component
{
    public string $timezone = 'America/Panama';
    public string $locale = 'es';
    public string $currency = 'USD';

    public function mount(): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();
        
        if ($settings) {
            $this->timezone = $settings->timezone;
            $this->locale = $settings->locale;
            $this->currency = $settings->currency;
        }
    }

    public function save(UpdateTenantLocalizationAction $action): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        $this->validate([
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'locale' => 'required|in:en,es',
            'currency' => 'required|string|size:3',
        ]);

        $action->execute(new LocalizationData(
            timezone: $this->timezone,
            locale: $this->locale,
            currency: $this->currency,
        ));

        session()->flash('status', __('Localization settings updated successfully.'));
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.localization-settings', [
            'timezones' => timezone_identifiers_list(),
            'locales' => [
                'en' => 'English',
                'es' => 'Español',
            ],
            'currencies' => [
                'USD' => 'US Dollar ($)',
                'EUR' => 'Euro (€)',
                'MXN' => 'Mexican Peso ($)',
                'COP' => 'Colombian Peso ($)',
                'BRL' => 'Brazilian Real (R$)',
            ],
        ]);
    }
}
