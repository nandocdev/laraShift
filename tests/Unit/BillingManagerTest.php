<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\InternalBillingProvider;
use App\Modules\Central\Billing\Infrastructure\Gateways\StripeBillingProvider;
use Tests\TestCase;

class BillingManagerTest extends TestCase
{
    public function test_it_can_create_stripe_driver()
    {
        $manager = app(BillingManager::class);
        $driver = $manager->driver('stripe');

        $this->assertInstanceOf(StripeBillingProvider::class, $driver);
    }

    public function test_it_can_create_paguelofacil_driver()
    {
        $manager = app(BillingManager::class);
        $driver = $manager->driver('paguelofacil');

        $this->assertInstanceOf(InternalBillingProvider::class, $driver);
    }

    public function test_it_can_create_dlocal_driver()
    {
        $manager = app(BillingManager::class);
        $driver = $manager->driver('dlocal');

        $this->assertInstanceOf(InternalBillingProvider::class, $driver);
    }
}
