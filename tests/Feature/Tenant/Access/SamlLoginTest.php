<?php

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\SsoSetting;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Interface\Livewire\Login;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => 'foo',
        'slug' => 'foo-saml',
        'name' => 'Foo',
        'email' => 'foo@example.com',
        'plan_id' => 'free',
    ]);

    $domain = 'foo-saml.localhost';
    $this->tenant->domains()->create(['domain' => $domain]);
    tenancy()->initialize($this->tenant);
    URL::forceRootUrl('http://'.$domain);

    $this->tenant->run(function () {
        $this->ssoSetting = SsoSetting::create([
            'idp_entity_id' => 'https://idp.example.com/metadata',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_x509_cert' => 'cert',
            'enforced_domains' => ['example.com'],
            'is_forced' => true,
            'is_tested' => true,
        ]);
    });
});

it('redirects to saml login when domain is enforced', function () {
    $this->tenant->run(function () {
        Livewire::test(Login::class)
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('saml.login'));
    });
});

it('does not redirect to saml login when domain is not enforced', function () {
    $this->tenant->run(function () {
        Livewire::test(Login::class)
            ->set('email', 'test@other.com')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['email']);
    });
});

it('creates a user just-in-time on saml callback', function () {
    $this->tenant->run(function () {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('new@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('New User');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('saml2')->andReturn($provider);

        $response = $this->post(route('saml.acs'));

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'New User',
        ]);

        $this->assertAuthenticatedAs(User::where('email', 'new@example.com')->first(), 'web');
    });
});
