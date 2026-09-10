<?php

declare(strict_types=1);

use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\CheckoutProvider;
use App\Modules\Platform\Contracts\Billing\PaymentProvider;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use App\Modules\Platform\Contracts\Billing\SubscriptionProvider;
use App\Modules\Platform\Contracts\Billing\WebhookProvider;
use Spatie\LaravelData\Data;

it('exposes the segregated billing contracts', function () {
    expect(interface_exists(BillingManager::class))->toBeTrue()
        ->and(interface_exists(CheckoutProvider::class))->toBeTrue()
        ->and(interface_exists(SubscriptionProvider::class))->toBeTrue()
        ->and(interface_exists(PaymentProvider::class))->toBeTrue()
        ->and(interface_exists(WebhookProvider::class))->toBeTrue()
        ->and(class_exists(PlanRef::class))->toBeTrue()
        ->and(is_subclass_of(PlanRef::class, Data::class))->toBeTrue();
});

it('keeps Platform free of Central imports', function () {
    $base = dirname(__DIR__, 2).'/app/Modules/Platform/Contracts/Billing';
    $files = glob($base.'/*.php');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        expect(file_get_contents($file))
            ->not->toContain('App\\Modules\\Central', "Platform file imports Central: {$file}");
    }
});

it('keeps provider conditionals out of Domain and Application', function () {
    $domainDir = dirname(__DIR__, 2).'/app/Modules/Central/Billing/Domain';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($domainDir, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toMatch('/\$\w*(gateway|provider)\s*===/', "Provider conditional in: {$file}");
    }
});

it('keeps gateway names out of Domain', function () {
    $domainDir = dirname(__DIR__, 2).'/app/Modules/Central/Billing/Domain';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($domainDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $contents = file_get_contents($file->getPathname());
            expect($contents)->not->toContain('Dlocal', "Gateway name in Domain: {$file->getPathname()}")
                ->and($contents)->not->toContain('Clave', "Gateway name in Domain: {$file->getPathname()}")
                ->and($contents)->not->toContain('Stripe', "Gateway name in Domain: {$file->getPathname()}");
        }
    }
});
