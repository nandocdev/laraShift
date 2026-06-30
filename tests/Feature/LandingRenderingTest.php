<?php

use App\Modules\Central\Landings\Actions\RenderLandingAction;
use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders a landing page with blocks', function () {
    $tenantId = (string) Str::uuid();
    // 1. Create a tenant
    $tenant = Tenant::create([
        'id' => $tenantId,
        'slug' => 'acme',
        'name' => 'Acme Corp',
        'email' => 'admin@acme.com',
        'status' => 'active',
    ]);

    // 2. Create a landing
    $landing = Landing::create([
        'tenant_id' => $tenant->id,
        'slug' => 'test-landing',
        'title' => 'Test Landing',
        'theme' => [
            'colors' => ['primary' => '#000000'],
        ],
        'blocks' => [
            [
                'id' => 'hero-1',
                'type' => 'hero',
                'variant' => 'centered',
                'order' => 0,
                'config' => [
                    'headline' => 'Welcome to Test',
                ],
            ],
        ],
    ]);

    // 3. Render
    $html = app(RenderLandingAction::class)->execute($landing);

    // 4. Assert
    expect($html)->toContain('Welcome to Test');
    expect($html)->toContain('Test Landing');
    expect($html)->toContain('--primary-color: #000000');
});
