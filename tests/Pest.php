<?php

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () {
        $class = get_class($this);
        if ((str_contains($class, 'Feature\\Auth') || str_contains($class, 'Feature\\Settings')) && ! str_contains($class, 'AuthenticationTest')) {
            $plan = Plan::firstOrCreate(['slug' => 'free'], [
                'name' => 'Free Plan',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'features' => [],
            ]);

            $tenant = Tenant::firstOrCreate(['id' => 'test-tenant'], [
                'slug' => 'test-tenant',
                'name' => 'Test Tenant',
                'email' => 'test@tenant.com',
                'plan_id' => 'free',
                'status' => 'active',
            ]);

            $domain = 'test-tenant.' . config('tenancy.central_domain');
            $tenant->domains()->firstOrCreate(['domain' => $domain]);

            tenancy()->initialize($tenant);
            Illuminate\Support\Facades\URL::forceRootUrl('http://' . $domain);
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
