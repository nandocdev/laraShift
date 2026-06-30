<?php

declare(strict_types=1);

use App\Modules\Central\Landings\Actions\RenderLandingAction;
use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('prevents view injection / LFI by filtering invalid block types', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'test-tenant-lfi',
        'name' => 'Test Tenant LFI',
        'email' => 'test@lfi.com',
        'status' => 'active',
    ]);

    $landing = Landing::create([
        'tenant_id' => $tenant->id,
        'slug' => 'test-lfi',
        'title' => 'Test LFI',
        'blocks' => [
            ['type' => 'valid-block', 'order' => 1],
            ['type' => '../invalid/block', 'order' => 2],
            ['type' => 'another_invalid_block', 'order' => 3], // underscores are not in the regex
        ],
    ]);

    $action = app(RenderLandingAction::class);

    try {
        $action->execute($landing);
    } catch (Exception $e) {
        // Blade will try to render 'landings::blocks.valid-block'
        expect($e->getMessage())->toContain('valid-block');
        expect($e->getMessage())->not->toContain('../invalid/block');
        expect($e->getMessage())->not->toContain('another_invalid_block');
    }
});
