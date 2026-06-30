<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Providers;

use App\Modules\Tenant\DataManagement\Livewire\ManageBackups;
use App\Modules\Tenant\DataManagement\Livewire\ManageDataImports;
use App\Modules\Tenant\DataManagement\Livewire\RetentionSettings;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class DataManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'data-management');

        Livewire::component('tenant-manage-imports', ManageDataImports::class);
        Livewire::component('tenant-manage-backups', ManageBackups::class);
        Livewire::component('tenant-retention-settings', RetentionSettings::class);
    }
}
