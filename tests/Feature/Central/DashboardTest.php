<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Http\Middleware\ValidateCentralSession;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Settings\Infrastructure\Services\CentralBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('unauthenticated user is redirected from central dashboard to central login', function () {
    $this->get(route('central.dashboard'))
        ->assertRedirect(route('central.login'));
});

test('authenticated central user can access dashboard and app logo renders correctly', function () {
    $this->withoutMiddleware(ValidateCentralSession::class);

    $user = CentralUser::factory()->create();

    CentralBranding::set('platform_name', 'LaraShift Test Suite');

    $this->actingAs($user, 'central')
        ->get(route('central.dashboard'))
        ->assertOk()
        ->assertSee('LaraShift Test Suite');
});
