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

    public function mount(): void
    {
        $settings = TenantSetting::firstOrCreate(
            ['tenant_id' => tenant('id')],
            ['name' => tenant('name')]
        );

        $this->name = $settings->name;
        $this->logo_path = $settings->logo_path ?? '';
        $this->primary_color = $settings->primary_color ?? '#4f46e5';
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'primary_color' => 'required|hex_color',
        ]);

        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();

        $data = [
            'name' => $this->name,
            'primary_color' => $this->primary_color,
        ];

        if ($this->logo) {
            $data['logo_path'] = $this->logo->store("tenant_" . tenant('id') . "/branding", 'public');
        }

        $settings->update($data);

        // Update Central Tenant record name if needed for consistency
        tenant()->update(['name' => $this->name]);

        session()->flash('status', __('Branding updated successfully.'));
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.branding-settings');
    }
}
