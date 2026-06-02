<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Modules\Central\Auth\Providers\CentralAuthServiceProvider::class,
    App\Modules\Central\Provisioning\Providers\ProvisioningServiceProvider::class,
    App\Modules\Central\Billing\Providers\BillingServiceProvider::class,
    App\Modules\Tenant\Identity\Providers\IdentityServiceProvider::class,
];
