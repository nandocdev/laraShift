<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Tenant\Settings\Actions\InitializeTenantLandingAction;
use App\Modules\Tenant\Settings\Actions\UpdateTenantBrandingAction;
use App\Modules\Tenant\Settings\DTOs\BrandingData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use App\Modules\Tenant\Settings\Support\BrandingPresets;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BrandingSettings extends Component
{
    use WithFileUploads;

    public string $name = '';
    public $logo;
    public string $logo_path = '';
    public string $primary_color = '#4f46e5';
    public string $theme_preset = 'saas';
    public bool $mfa_required = false;

    public function updatedThemePreset($value): void
    {
        $presets = BrandingPresets::all();
        if ($value !== 'custom' && isset($presets[$value])) {
            $this->primary_color = $presets[$value]['primary'];
        }
    }

    public function initializeLanding(InitializeTenantLandingAction $action): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        $action->execute($this->theme_preset, $this->primary_color);

        session()->flash('status', __('Landing page initialized!'));
        $this->dispatch('toast', heading: __('Landing Page'), text: __('Landing page initialized!'), variant: 'success');
    }

    public function updatedLogo(): void
    {
        try {
            $this->validate([
                'logo' => 'image|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
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

        // Detect preset based on primary color
        $this->theme_preset = 'custom';
        foreach (BrandingPresets::all() as $key => $preset) {
            if ($preset['primary'] === $this->primary_color) {
                $this->theme_preset = $key;
                break;
            }
        }
    }

    public function save(UpdateTenantBrandingAction $action): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        try {
            $this->validate([
                'name' => 'required|string|max:255',
                'logo' => 'nullable|image|max:2048',
                'primary_color' => 'required|hex_color',
                'theme_preset' => 'required|string',
                'mfa_required' => 'boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
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

        // Refresh local state
        $this->logo_path = $settings->fresh()->logo_path ?? '';
        $this->reset('logo');

        session()->flash('status', __('Branding updated successfully.'));
        $this->dispatch('toast', heading: __('Settings Updated'), text: __('Branding updated successfully.'), variant: 'success');
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.branding-settings', [
            'presets' => BrandingPresets::all()
        ]);
    }
}
