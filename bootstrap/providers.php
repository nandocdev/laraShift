<?php

use App\Modules\Central\Auth\Providers\CentralAuthServiceProvider;
use App\Modules\Central\Billing\Providers\BillingServiceProvider;
use App\Modules\Central\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Central\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Central\Operations\Providers\OperationsServiceProvider;
use App\Modules\Central\Provisioning\Providers\ProvisioningServiceProvider;
use App\Modules\Central\Support\Providers\SupportServiceProvider;
use App\Modules\Platform\UI\Providers\UiServiceProvider;
use App\Modules\Tenant\Access\Providers\AccessServiceProvider;
use App\Modules\Tenant\Audit\Providers\AuditServiceProvider;
use App\Modules\Tenant\Experience\Providers\ExperienceServiceProvider;
use App\Modules\Tenant\Integrations\Providers\IntegrationsServiceProvider;
use App\Modules\Tenant\Workspace\Providers\WorkspaceServiceProvider;
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
    CatalogServiceProvider::class,
    OperationsServiceProvider::class,
    SupportServiceProvider::class,
    MarketingServiceProvider::class,
    UiServiceProvider::class,
    AccessServiceProvider::class,
    WorkspaceServiceProvider::class,
    ExperienceServiceProvider::class,
    IntegrationsServiceProvider::class,
    AuditServiceProvider::class,
];
