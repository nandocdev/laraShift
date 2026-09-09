<?php

use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Str;

it('rejects forged paguelofacil callback without gateway verification', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid(),
        'slug' => 'test-forge-'.Str::random(5),
        'name' => 'Test Tenant',
        'email' => 'forge@example.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
    $this->get("/central/billing/paguelofacil/callback?PARM_1={$tenant->id}&PARM_2=pro&Estado=Aprobada&codOper=FAKE&amount=100")
        ->assertRedirectContains('success');
    expect(Subscription::where('tenant_id', $tenant->id)->where('status', 'active')->exists())->toBeFalse();
    expect($tenant->fresh()->plan_id)->not->toBe('pro');
});
