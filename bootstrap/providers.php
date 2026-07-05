<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Modules\Central\Auth\Providers\CentralAuthServiceProvider::class,
    App\Modules\Central\Provisioning\Providers\ProvisioningServiceProvider::class,
    App\Modules\Central\Billing\Providers\BillingServiceProvider::class,
    App\Modules\Central\Settings\Providers\CentralSettingsServiceProvider::class,
    App\Modules\Central\Features\Providers\FeaturesServiceProvider::class,
    App\Modules\Central\Landings\Providers\LandingServiceProvider::class,
    App\Modules\Central\Support\Providers\SupportServiceProvider::class,
    App\Modules\Central\Payments\Providers\PaymentsServiceProvider::class,
    App\Modules\Central\Infrastructure\Providers\InfrastructureServiceProvider::class,
    App\Modules\Central\Marketing\Providers\MarketingServiceProvider::class,
    App\Modules\Tenant\Access\Providers\AccessServiceProvider::class,
    App\Modules\Tenant\Experience\Providers\ExperienceServiceProvider::class,
    App\Modules\Tenant\Integrations\Providers\IntegrationsServiceProvider::class,
    App\Modules\Tenant\Audit\Providers\AuditServiceProvider::class,
];
