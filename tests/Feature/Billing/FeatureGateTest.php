<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\GenerateApiKey;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Interface\Livewire\ManageApiKeys;
use Illuminate\Support\Str;
use Livewire\Livewire;

function featureGateContext(string $slug, array $features): array
{
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => 'Gate Test',
        'email' => $slug.'@test.com',
        'status' => 'active',
        'billing_gateway' => 'dlocal',
        'plan_id' => $slug.'-plan',
    ]);
    $domain = $slug.'.'.parse_url(config('app.url'), PHP_URL_HOST);
    $tenant->domains()->create(['domain' => $domain]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    Plan::create([
        'name' => $slug, 'slug' => $slug.'-plan', 'price_monthly' => 1000, 'price_yearly' => 10000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => $features, 'gateway_ids' => [], 'quotas' => []],
        'is_active' => true,
    ]);

    return [$tenant, $domain, $user];
}

it('blocks the api keys page for plans without api_access', function () {
    [, $domain, $user] = featureGateContext('gate-free', ['basic_dashboard']);

    $this->actingAs($user)->get('http://'.$domain.'/settings/api-keys')->assertForbidden();
});

it('allows the api keys page for plans with api_access', function () {
    [, $domain, $user] = featureGateContext('gate-pro', ['basic_dashboard', 'api_access']);

    $this->actingAs($user)->get('http://'.$domain.'/settings/api-keys')->assertOk();
});

it('hides gated sidebar entries without the feature', function () {
    [, $domain, $user] = featureGateContext('gate-nav-free', ['basic_dashboard']);

    $this->actingAs($user)->get('http://'.$domain.'/dashboard')
        ->assertOk()
        ->assertDontSee('/settings/api-keys', false);
});

it('shows gated sidebar entries with the feature', function () {
    [, $domain, $user] = featureGateContext('gate-nav-pro', ['basic_dashboard', 'api_access']);

    $this->actingAs($user)->get('http://'.$domain.'/dashboard')
        ->assertOk()
        ->assertSee('/settings/api-keys', false);
});

it('blocks api key generation over Livewire without api_access', function () {
    [$tenant, $domain, $user] = featureGateContext('gate-live', ['basic_dashboard', 'api_access']);
    tenancy()->initialize($tenant);

    try {
        $component = Livewire::actingAs($user)->test(
            ManageApiKeys::class
        );

        // Plan downgraded mid-session: the XHR path must re-check.
        $plan = Plan::where('slug', 'gate-live-plan')->firstOrFail();
        $features = $plan->features;
        $features['display_features'] = ['basic_dashboard'];
        $plan->update(['features' => $features]);

        $component->call('generate')->assertForbidden();
    } finally {
        tenancy()->end();
    }
});

it('blocks the stateless api without api_access', function () {
    [$tenant, $domain, $user] = featureGateContext('gate-api', ['basic_dashboard']);
    tenancy()->initialize($tenant);

    try {
        $key = app(GenerateApiKey::class)->execute('test', ['identity:read'], $user)['key'];
    } finally {
        tenancy()->end();
    }

    $this->withHeaders(['Authorization' => 'Bearer '.$key])
        ->get('http://'.$domain.'/api/me')
        ->assertForbidden();
});

it('allows the stateless api with api_access', function () {
    [$tenant, $domain, $user] = featureGateContext('gate-api-pro', ['api_access']);
    tenancy()->initialize($tenant);

    try {
        $key = app(GenerateApiKey::class)->execute('test', ['identity:read'], $user)['key'];
    } finally {
        tenancy()->end();
    }

    $this->withHeaders(['Authorization' => 'Bearer '.$key])
        ->get('http://'.$domain.'/api/me')
        ->assertOk();
});
