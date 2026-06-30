<?php

use App\Modules\Central\Auth\Providers\CentralAuthServiceProvider;
use App\Modules\Central\Billing\Providers\BillingServiceProvider;
use App\Modules\Central\Features\Providers\FeaturesServiceProvider;
use App\Modules\Central\Infrastructure\Providers\InfrastructureServiceProvider;
use App\Modules\Central\Landings\Providers\LandingServiceProvider;
use App\Modules\Central\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Central\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Central\Provisioning\Providers\ProvisioningServiceProvider;
use App\Modules\Central\Settings\Providers\CentralSettingsServiceProvider;
use App\Modules\Central\Support\Providers\SupportServiceProvider;
use App\Modules\Tenant\Audit\Providers\AuditServiceProvider;
use App\Modules\Tenant\Identity\Providers\IdentityServiceProvider;
use App\Modules\Tenant\Notifications\Providers\NotificationsServiceProvider;
use App\Modules\Tenant\Settings\Providers\SettingsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TenancyServiceProvider::class,
    CentralAuthServiceProvider::class,
    ProvisioningServiceProvider::class,
    BillingServiceProvider::class,
    CentralSettingsServiceProvider::class,
    FeaturesServiceProvider::class,
    LandingServiceProvider::class,
    SupportServiceProvider::class,
    PaymentsServiceProvider::class,
    InfrastructureServiceProvider::class,
    MarketingServiceProvider::class,
    IdentityServiceProvider::class,
    SettingsServiceProvider::class,
    NotificationsServiceProvider::class,
    AuditServiceProvider::class,
];
