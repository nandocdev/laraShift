<?php

declare(strict_types=1);

use App\Modules\Shared\Tenancy\Services\CentralFallback;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->fallback = app(CentralFallback::class);
});

test('detects central domain request', function () {
    $request = Request::create('http://larashift.test');

    expect($this->fallback->isCentralRequest($request))->toBeTrue();
});

test('detects localhost as central', function () {
    $request = Request::create('http://localhost');

    expect($this->fallback->isCentralRequest($request))->toBeTrue();
});

test('detects tenant subdomain as non-central', function () {
    config(['tenancy.central_domains' => ['larashift.test']]);

    $request = Request::create('http://tenant-one.larashift.test');

    expect($this->fallback->isCentralRequest($request))->toBeFalse();
});

test('generates central url', function () {
    config(['tenancy.central_domain' => 'app.larashift.test']);

    $url = $this->fallback->centralUrl('admin/dashboard');

    expect($url)->toContain('app.larashift.test');
    expect($url)->toContain('admin/dashboard');
});
