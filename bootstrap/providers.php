<?php

use App\Modules\Central\Auth\Providers\CentralAuthServiceProvider;
use App\Modules\Central\Billing\Providers\BillingServiceProvider;
use App\Modules\Central\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Central\Growth\Providers\GrowthServiceProvider;
use App\Modules\Central\Operations\Providers\OperationsServiceProvider;
use App\Modules\Central\Provisioning\Providers\ProvisioningServiceProvider;
use App\Modules\Central\Settings\Providers\SettingsServiceProvider;
use App\Modules\Central\Support\Providers\SupportServiceProvider;
use App\Modules\Platform\Integrations\Dlocal\Providers\DlocalServiceProvider;
use App\Modules\Platform\UI\Providers\UiServiceProvider;
use App\Modules\Tenant\Access\Providers\AccessServiceProvider;
use App\Modules\Tenant\Compliance\Providers\ComplianceServiceProvider;
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
    SettingsServiceProvider::class,
    SupportServiceProvider::class,
    GrowthServiceProvider::class,
    UiServiceProvider::class,
    DlocalServiceProvider::class,
    AccessServiceProvider::class,
    WorkspaceServiceProvider::class,
    ExperienceServiceProvider::class,
    IntegrationsServiceProvider::class,
    ComplianceServiceProvider::class,
];
