<?php

declare(strict_types=1);

use App\Modules\Platform\Security\ApiKeys\ApiKeyHasher;

beforeEach(function () {
    $this->hasher = new ApiKeyHasher;
});

test('generates a key with tnt_ prefix', function () {
    $key = $this->hasher->generate();

    expect($key)->toMatch('/^tnt_[a-f0-9]{64}$/');
});

test('hashes consistently', function () {
    $key = $this->hasher->generate();
    expect($this->hasher->hash($key))->toBe($this->hasher->hash($key));
});

test('verify returns false for wrong key', function () {
    $key = $this->hasher->generate();
    $hash = $this->hasher->hash($key);
    expect($this->hasher->verify('wrong-key', $hash))->toBeFalse();
});

test('isValidFormat accepts tnt_ prefix', function () {
    expect($this->hasher->isValidFormat('tnt_abc123'))->toBeTrue();
    expect($this->hasher->isValidFormat('sk_test_abc'))->toBeFalse();
});
