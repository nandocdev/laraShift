<?php

namespace Database\Seeders;

use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Seeder;

class LandingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::first();

        if (!$tenant) {
            $this->command->error('No tenant found. Run TenantSeeder first.');
            return;
        }

        Landing::updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'saas-landing'],
            [
                'title' => 'My SaaS Product',
                'status' => 'draft',
                'theme' => [
                    'colors' => [
                        'primary' => '#4f46e5',
                        'secondary' => '#1e293b',
                    ],
                    'typography' => [
                        'font_heading' => 'Inter',
                        'font_body' => 'Inter',
                    ]
                ],
                'blocks' => [
                    [
                        'id' => 'hero-1',
                        'type' => 'hero',
                        'variant' => 'split',
                        'order' => 0,
                        'config' => [
                            'headline' => 'Automate your business with LaraShift',
                            'subtitle' => 'The ultimate platform for multi-tenant SaaS applications in Laravel.',
                            'button_primary_text' => 'Get Started',
                            'button_primary_url' => '/register',
                            'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&q=80&w=2426&ixlib=rb-4.0.3',
                        ],
                        'styles' => ['padding' => 'xl']
                    ],
                    [
                        'id' => 'cta-1',
                        'type' => 'cta',
                        'variant' => 'centered',
                        'order' => 1,
                        'config' => [
                            'headline' => 'Ready to grow your revenue?',
                            'description' => 'Join 2,000+ businesses using our platform to scale their operations.',
                            'button_primary_text' => 'Start Free Trial',
                            'button_primary_url' => '/trial',
                        ],
                        'styles' => ['background' => 'dark']
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'variant' => 'simple',
                        'order' => 2,
                        'config' => [
                            'copyright_text' => '© 2026 LaraShift Inc.',
                            'legal_links' => [
                                ['label' => 'Privacy', 'url' => '/privacy'],
                                ['label' => 'Terms', 'url' => '/terms'],
                            ]
                        ]
                    ]
                ]
            ]
        );

        $landing = Landing::where('slug', 'saas-landing')->first();
        app(\App\Modules\Central\Landings\Actions\PublishLandingAction::class)->execute($landing);

        $this->command->info('Landing seeder finished and published.');
    }
}
