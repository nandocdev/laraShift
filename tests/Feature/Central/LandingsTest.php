<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Landings\Actions\PublishLandingAction;
use App\Modules\Central\Landings\Actions\RenderLandingAction;
use App\Modules\Central\Landings\Actions\SaveLandingAction;
use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Central\Landings\Models\LandingVersion;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'landing-test-' . Str::random(4),
        'name' => 'Landing Test',
        'email' => 'landing@test.com',
        'plan_id' => 'free',
    ]);

    $this->landing = Landing::create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'test-landing',
        'title' => 'Test Landing',
        'theme' => ['colors' => ['primary' => '#000000']],
        'blocks' => [
            ['id' => 'hero-1', 'type' => 'hero', 'variant' => 'centered', 'order' => 0,
                'config' => ['headline' => 'Welcome']],
        ],
        'status' => 'draft',
    ]);
});

test('save action updates landing blocks and theme', function () {
    $action = app(SaveLandingAction::class);

    $newBlocks = [['id' => 'hero-2', 'type' => 'hero', 'order' => 0, 'config' => ['headline' => 'Updated']]];
    $newTheme = ['colors' => ['primary' => '#ff0000']];

    $action->execute($this->landing, $newBlocks, $newTheme);

    $this->landing->refresh();
    expect($this->landing->blocks)->toBe($newBlocks);
    expect($this->landing->theme)->toBe($newTheme);
});

test('publish action renders html and creates version snapshot', function () {
    $action = app(PublishLandingAction::class);

    $result = $action->execute($this->landing, null);

    $this->landing->refresh();
    expect($this->landing->status)->toBe('published');
    expect($this->landing->published_html)->not->toBeNull();
    expect($this->landing->published_at)->not->toBeNull();

    $version = LandingVersion::where('landing_id', $this->landing->id)->first();
    expect($version)->not->toBeNull();
    expect($version->blocks_snapshot)->toBe($this->landing->blocks);
});

test('publishes html contains landing content', function () {
    $action = app(PublishLandingAction::class);

    $action->execute($this->landing, null);

    $this->landing->refresh();
    expect($this->landing->published_html)->toContain('Welcome');
    expect($this->landing->published_html)->toContain('Test Landing');
});

test('render action produces valid html', function () {
    $html = app(RenderLandingAction::class)->execute($this->landing);

    expect($html)->toContain('Welcome');
    expect($html)->toContain('Test Landing');
    expect($html)->toContain('--primary-color: #000000');
});

test('prevents view injection by filtering invalid block types', function () {
    $landing = Landing::create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'lfi-test',
        'title' => 'LFI Test',
        'blocks' => [
            ['type' => '../invalid/block', 'order' => 1],
            ['type' => 'another_invalid_block', 'order' => 2],
        ],
    ]);

    $action = app(RenderLandingAction::class);

    $html = $action->execute($landing);

    expect($html)->not->toContain('../invalid/block');
    expect($html)->not->toContain('another_invalid_block');
});

test('save action is idempotent when saving same data', function () {
    $action = app(SaveLandingAction::class);

    $action->execute($this->landing, $this->landing->blocks, $this->landing->theme);
    $landingAfterFirstSave = $this->landing->fresh();

    $action->execute($landingAfterFirstSave, $landingAfterFirstSave->blocks, $landingAfterFirstSave->theme);

    expect($landingAfterFirstSave->fresh()->blocks)->toBe($this->landing->blocks);
});

test('publish action does not overwrite existing published version', function () {
    $action = app(PublishLandingAction::class);

    $action->execute($this->landing, null);

    $this->landing->refresh();
    $firstHtml = $this->landing->published_html;

    $action->execute($this->landing, null);

    $this->landing->refresh();
    expect(LandingVersion::where('landing_id', $this->landing->id)->count())->toBe(2);
});
