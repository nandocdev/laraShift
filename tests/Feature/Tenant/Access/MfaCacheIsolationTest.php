<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Support\Str;

function mfaIsolationContext(string $slug, bool $mfaRequired): array
{
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => 'MFA Test',
        'email' => $slug.'@test.com',
        'status' => 'active',
    ]);
    $domain = $slug.'.'.parse_url(config('app.url'), PHP_URL_HOST);
    $tenant->domains()->create(['domain' => $domain]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    TenantSetting::create([
        'tenant_id' => $tenant->id,
        'mfa_required' => $mfaRequired,
    ]);

    return [$domain, $user];
}

it('does not leak one tenant mfa setting into another tenant', function () {
    [$domainA, $userA] = mfaIsolationContext('mfa-a', true);
    [$domainB, $userB] = mfaIsolationContext('mfa-b', false);

    // Tenant A requires MFA: redirected to enrollment.
    $this->actingAs($userA)->get('http://'.$domainA.'/dashboard')
        ->assertRedirect(route('tenant.settings.security.2fa'));

    // Tenant B must be unaffected by A's cached setting.
    $this->actingAs($userB)->get('http://'.$domainB.'/dashboard')->assertOk();
});
