<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Livewire;

use App\Modules\Central\Settings\Support\CentralBranding;
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
        $this->validate([
            'platformName' => 'required|string|min:3',
            'primaryColor' => 'required|hex_color',
            'logoUrl' => 'nullable|url',
        ]);

        CentralBranding::set('platform_name', $this->platformName);
        CentralBranding::set('primary_color', $this->primaryColor);
        CentralBranding::set('logo_url', $this->logoUrl);

        session()->flash('status', __('Platform branding updated successfully.'));
    }

    public function render(): View
    {
        return view('settings::pages.platform-branding');
    }
}
