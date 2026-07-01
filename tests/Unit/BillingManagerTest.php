<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Billing\Support\Drivers\DlocalBillingProvider;
use App\Modules\Central\Billing\Support\Drivers\InternalBillingProvider;
use App\Modules\Central\Billing\Support\Drivers\StripeBillingProvider;
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

        $this->assertInstanceOf(DlocalBillingProvider::class, $driver);
    }
}
