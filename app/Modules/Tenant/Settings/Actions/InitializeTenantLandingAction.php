<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Actions;

use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Tenant\Settings\Support\BrandingPresets;

final readonly class InitializeTenantLandingAction
{
    /**
     * Initializes a default landing page for the tenant.
     */
    public function execute(string $themePreset = 'saas', ?string $customPrimaryColor = null): Landing
    {
        $tenant = tenant();
        $preset = BrandingPresets::get($themePreset);
        $primaryColor = $themePreset === 'custom' ? $customPrimaryColor : $preset['primary'];
        
        return Landing::firstOrCreate(
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
    }
}
