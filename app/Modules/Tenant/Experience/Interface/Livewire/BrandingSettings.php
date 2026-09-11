<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Interface\Livewire;

use App\Modules\Tenant\Experience\Application\Actions\InitializeTenantLanding;
use App\Modules\Tenant\Experience\Application\Actions\UpdateTenantBranding;
use App\Modules\Tenant\Experience\Application\DTO\BrandingData;
use App\Modules\Tenant\Experience\Domain\Models\Landing;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use App\Modules\Tenant\Experience\Infrastructure\Support\BrandingPresets;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BrandingSettings extends Component
{
    use WithFileUploads;

    public string $name = '';

    public $logo = null;

    public string $logo_path = '';

    public string $primary_color = '#4f46e5';

    public string $theme_preset = 'saas';

    public bool $mfa_required = false;

    public function mount(): void
    {
        $settings = TenantSetting::firstOrCreate(
            ['tenant_id' => tenant('id')],
            ['name' => tenant('name')]
        );

        $this->name = $settings->name;
        $this->logo_path = $settings->logo_path ?? '';
        $this->primary_color = $settings->primary_color ?? '#4f46e5';
        $this->mfa_required = (bool) ($settings->mfa_required ?? false);

        $this->theme_preset = 'custom';
        foreach (BrandingPresets::all() as $key => $preset) {
            if ($preset['primary'] === $this->primary_color) {
                $this->theme_preset = $key;
                break;
            }
        }
    }

    /**
     * @return array<string, array{name: string, primary: ?string, secondary: string, font_heading: string, font_body: string}>
     */
    #[Computed]
    public function presets(): array
    {
        return BrandingPresets::all();
    }

    #[Computed]
    public function landing(): ?Landing
    {
        return Landing::query()
            ->where('tenant_id', tenant('id'))
            ->where('slug', 'saas-landing')
            ->first();
    }

    public function updatedThemePreset(string $value): void
    {
        $presets = $this->presets();
        if ($value !== 'custom' && isset($presets[$value]) && $presets[$value]['primary'] !== null) {
            $this->primary_color = $presets[$value]['primary'];
        }
    }

    public function updatedLogo(): void
    {
        try {
            $this->validate([
                'logo' => ['image', 'max:2048'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->reset('logo');
            $this->addError('logo', __('The uploaded file could not be processed. Please try again.'));
        }
    }

    public function getLogoPreviewUrlProperty(): ?string
    {
        if (! $this->logo || $this->getErrorBag()->has('logo')) {
            return null;
        }

        try {
            return $this->logo->temporaryUrl();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function save(UpdateTenantBranding $action): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        try {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'logo' => ['nullable', 'image', 'max:2048'],
                'primary_color' => ['required', 'hex_color'],
                'theme_preset' => ['required', 'string', Rule::in(array_keys($this->presets()))],
                'mfa_required' => ['boolean'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->reset('logo');
            $this->addError('logo', __('The uploaded file could not be processed. Please try again.'));

            return;
        }

        $action->execute(new BrandingData(
            name: $this->name,
            primaryColor: $this->primary_color,
            themePreset: $this->theme_preset,
            mfaRequired: $this->mfa_required,
            logo: $this->logo,
        ));

        $this->logo_path = $settings->fresh()->logo_path ?? '';
        $this->reset('logo');

        session()->flash('status', __('Branding updated successfully.'));
        $this->dispatch('toast', heading: __('Settings Updated'), text: __('Branding updated successfully.'), variant: 'success');
    }

    public function initializeLanding(InitializeTenantLanding $action): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        $action->execute($this->theme_preset, $this->primary_color);
        unset($this->landing);

        session()->flash('status', __('Landing page initialized!'));
        $this->dispatch('toast', heading: __('Landing Page'), text: __('Landing page initialized!'), variant: 'success');
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.branding-settings');
    }
}
