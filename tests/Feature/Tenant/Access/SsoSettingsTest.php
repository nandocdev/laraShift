<?php

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\SsoSetting;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Interface\Livewire\SsoSettings as SsoSettingsComponent;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => 'foo',
        'slug' => 'foo-sso',
        'name' => 'Foo',
        'email' => 'foo@example.com',
    ]);
    $this->tenant->run(function () {
        $this->user = User::factory()->create(['status' => 'active']);
    });
});

it('can save sso settings', function () {
    $this->tenant->run(function () {
        Livewire::actingAs($this->user)
            ->test(SsoSettingsComponent::class)
            ->set('idp_entity_id', 'https://idp.example.com')
            ->set('idp_sso_url', 'https://idp.example.com/login')
            ->set('idp_x509_cert', 'cert-data')
            ->set('enforced_domains', 'example.com, test.com')
            ->set('is_forced', false)
            ->call('save')
            ->assertHasNoErrors();

        $setting = SsoSetting::first();
        expect($setting->idp_entity_id)->toBe('https://idp.example.com');
        expect($setting->enforced_domains)->toBe(['example.com', 'test.com']);
    });
});

it('cannot enforce sso if not tested', function () {
    $this->tenant->run(function () {
        Livewire::actingAs($this->user)
            ->test(SsoSettingsComponent::class)
            ->set('idp_entity_id', 'https://idp.example.com')
            ->set('idp_sso_url', 'https://idp.example.com/login')
            ->set('idp_x509_cert', 'cert-data')
            ->set('enforced_domains', 'example.com')
            ->set('is_forced', true)
            ->call('save')
            ->assertHasErrors('is_forced');
    });
});
