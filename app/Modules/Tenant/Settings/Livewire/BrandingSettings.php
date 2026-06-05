<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
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

    // Curated Professional Palettes
    public array $presets = [
        'saas' => [
            'name' => 'SaaS Blue',
            'primary' => '#4f46e5', // Indigo 600
            'secondary' => '#1e293b', // Slate 800
            'font_heading' => 'Inter',
            'font_body' => 'Inter',
        ],
        'corporate' => [
            'name' => 'Corporate Slate',
            'primary' => '#0f172a', // Slate 900
            'secondary' => '#475569', // Slate 600
            'font_heading' => 'Montserrat',
            'font_body' => 'Inter',
        ],
        'startup' => [
            'name' => 'Startup Emerald',
            'primary' => '#10b981', // Emerald 500
            'secondary' => '#111827', // Zinc 900
            'font_heading' => 'Plus Jakarta Sans',
            'font_body' => 'Inter',
        ],
        'creative' => [
            'name' => 'Creative Rose',
            'primary' => '#e11d48', // Rose 600
            'secondary' => '#171717', // Neutral 900
            'font_heading' => 'Playfair Display',
            'font_body' => 'Lato',
        ],
        'custom' => [
            'name' => 'Custom Colors',
            'primary' => null, // Uses $this->primary_color
            'secondary' => '#1e293b',
            'font_heading' => 'Inter',
            'font_body' => 'Inter',
        ]
    ];

    public function updatedThemePreset($value): void
    {
        if ($value !== 'custom' && isset($this->presets[$value])) {
            $this->primary_color = $this->presets[$value]['primary'];
        }
    }

    public function initializeLanding(): void
    {
        $tenant = tenant();
        $preset = $this->presets[$this->theme_preset] ?? $this->presets['saas'];
        $primaryColor = $this->theme_preset === 'custom' ? $this->primary_color : $preset['primary'];
        
        $landing = Landing::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'saas-landing'],
            [
                'title' => $tenant->name . ' Landing',
                'status' => 'draft',
                'theme' => [
                    'colors' => [
                        'primary' => $primaryColor,
                        'secondary' => $preset['secondary'],
                    ],
                    'typography' => [
                        'font_heading' => $preset['font_heading'],
                        'font_body' => $preset['font_body'],
                    ]
                ],
                'blocks' => [
                    [
                        'id' => 'hero-initial',
                        'type' => 'hero',
                        'variant' => 'centered',
                        'order' => 0,
                        'config' => [
                            'headline' => 'Welcome to ' . $tenant->name,
                            'subtitle' => 'This is your new public landing page. You can edit this content in the Visual Builder.',
                            'button_primary_text' => 'Get Started',
                        ],
                        'styles' => ['padding' => 'xl']
                    ],
                    [
                        'id' => 'footer-initial',
                        'type' => 'footer',
                        'variant' => 'simple',
                        'order' => 1,
                        'config' => [
                            'copyright_text' => '© ' . date('Y') . ' ' . $tenant->name,
                        ]
                    ]
                ]
            ]
        );

        session()->flash('status', __('Landing page initialized!'));
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

        // Detect preset based on primary color
        $this->theme_preset = 'custom';
        foreach ($this->presets as $key => $preset) {
            if ($preset['primary'] === $this->primary_color) {
                $this->theme_preset = $key;
                break;
            }
        }
    }

    public function save(): void
    {
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

        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();

        $data = [
            'name' => $this->name,
            'primary_color' => $this->primary_color,
            'mfa_required' => $this->mfa_required,
        ];

        if ($this->logo) {
            // Delete previous logo if exists
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            // Disk is already tenant-scoped by FilesystemTenancyBootstrapper
            $data['logo_path'] = $this->logo->store('branding', 'public');
        }

        $settings->update($data);

        // Update landing page theme if it exists
        $landing = Landing::where('tenant_id', tenant('id'))->where('slug', 'saas-landing')->first();
        if ($landing) {
            $preset = $this->presets[$this->theme_preset] ?? $this->presets['custom'];
            $theme = $landing->theme ?? [];
            
            $theme['colors']['primary'] = $this->primary_color;
            $theme['colors']['secondary'] = $preset['secondary'];
            $theme['typography']['font_heading'] = $preset['font_heading'];
            $theme['typography']['font_body'] = $preset['font_body'];
            
            $landing->update(['theme' => $theme]);
        }

        // Refresh local state so the view renders the saved logo immediately
        $this->logo_path = $settings->fresh()->logo_path ?? '';
        $this->reset('logo');

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
