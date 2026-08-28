<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Interface\Livewire;

use App\Modules\Central\Settings\Infrastructure\Services\CentralBranding;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class PlatformBranding extends Component
{
    public string $platformName;

    public string $primaryColor;

    public string $logoUrl;

    public function mount(): void
    {
        $this->platformName = CentralBranding::platformName();
        $this->primaryColor = CentralBranding::primaryColor();
        $this->logoUrl = CentralBranding::logoUrl() ?? '';
    }

    public function save(): void
    {
        Gate::authorize('branding:manage');

        $this->validate([
            'platformName' => 'required|string|min:3',
            'primaryColor' => 'required|hex_color',
            'logoUrl' => ['nullable', 'url', 'regex:/^https:\/\//'],
        ]);

        // Normalize primaryColor to lowercase 6-hex
        $normalizedColor = strtolower($this->primaryColor);
        if (preg_match('/^#[0-9a-f]{3}$/', $normalizedColor)) {
            $normalizedColor = '#'.$normalizedColor[1].$normalizedColor[1].$normalizedColor[2].$normalizedColor[2].$normalizedColor[3].$normalizedColor[3];
        }
        $this->primaryColor = $normalizedColor;

        CentralBranding::set('platform_name', $this->platformName);
        CentralBranding::set('primary_color', $this->primaryColor);
        CentralBranding::set('logo_url', $this->logoUrl);

        activity('settings')
            ->causedBy(auth('central')->user())
            ->withProperties(['platform_name' => $this->platformName, 'primary_color' => $this->primaryColor, 'logo_url' => $this->logoUrl])
            ->log('branding_updated');

        session()->flash('status', __('Platform branding updated successfully.'));
    }

    public function render(): View
    {
        return view('settings::pages.platform-branding');
    }
}
