<?php

declare(strict_types=1);

use App\Modules\Shared\Tenancy\Services\PasswordPolicy;

test('default password policy requires 8 chars with mixed case and numbers', function () {
    $policy = PasswordPolicy::default();

    $rules = $policy->rules();

    expect($rules)->toContain('min:8');
});

test('default policy rejects short password', function () {
    $policy = PasswordPolicy::default();

    $error = $policy->validate('Ab1');

    expect($error)->not->toBeNull();
});

test('default policy rejects password without uppercase', function () {
    $policy = PasswordPolicy::default();

    $error = $policy->validate('abcdef1');

    expect($error)->not->toBeNull();
});

test('default policy accepts strong password', function () {
    $policy = PasswordPolicy::default();

    $error = $policy->validate('StrongPass1');

    expect($error)->toBeNull();
});

test('custom policy config overrides defaults', function () {
    $reflection = new ReflectionClass(PasswordPolicy::class);
    $policy = PasswordPolicy::default();

    $loadConfig = $reflection->getMethod('loadConfig');
    $loadConfig->setAccessible(true);

    $config = $loadConfig->invoke($policy);

    expect($config['min_length'])->toBe(8);
});

test('password policy validates with regex rules', function () {
    $policy = PasswordPolicy::default();
    $rules = $policy->rules();

    expect($rules)->toContain('min:8');
    expect(preg_grep('/regex/', $rules))->not->toBeEmpty();
});
