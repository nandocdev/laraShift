<?php

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'guest-'.substr($id, 0, 8),
        'name' => 'Guest Tenant',
        'email' => 'guest-'.substr($id, 0, 8).'@test.com',
        'status' => 'active',
    ]);
    $domain = 'guest-'.substr($id, 0, 8).'.'.parse_url(config('app.url'), PHP_URL_HOST);
    $tenant->domains()->create(['domain' => $domain]);

    $response = $this->get('http://'.$domain.'/dashboard');
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'auth-'.substr($id, 0, 8),
        'name' => 'Auth Tenant',
        'email' => 'auth-'.substr($id, 0, 8).'@test.com',
        'status' => 'active',
    ]);
    $domain = 'auth-'.substr($id, 0, 8).'.'.parse_url(config('app.url'), PHP_URL_HOST);
    $tenant->domains()->create(['domain' => $domain]);

    $response = $this->get('http://'.$domain.'/dashboard');
    $response->assertRedirect(route('login'));
});
