<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Contracts\View\View;
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
    public bool $mfa_required = false;

    public function updatedLogo(): void
    {
        try {
            $this->validate([
                'logo' => 'image|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Handle Flysystem errors (missing metadata/file) or other upload failures
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
    }

    public function save(): void
    {
        try {
            $this->validate([
                'name' => 'required|string|max:255',
                'logo' => 'nullable|image|max:2048',
                'primary_color' => 'required|hex_color',
                'mfa_required' => 'boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->reset('logo');
            $this->addError('logo', __('The uploaded file could not be processed. Please try again.'));
            return;
        }

        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();

        $data = [
            'name' => $this->name,
            'primary_color' => $this->primary_color,
            'mfa_required' => $this->mfa_required,
        ];

        if ($this->logo) {
            $data['logo_path'] = $this->logo->store("tenant_" . tenant('id') . "/branding", 'public');
        }

        $settings->update($data);

        // Update Central Tenant record name if needed for consistency
        tenant()->update(['name' => $this->name]);

        // Fire Events
        event(new \App\Modules\Shared\Events\TenantSettingsUpdated(tenant('id'), array_keys($data)));
        
        if (isset($data['mfa_required'])) {
            event(new \App\Modules\Shared\Events\TenantMfaRequirementChanged(tenant('id'), (bool)$data['mfa_required']));
        }

        session()->flash('status', __('Branding updated successfully.'));
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.branding-settings');
    }
}
