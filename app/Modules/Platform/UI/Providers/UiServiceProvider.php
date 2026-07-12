<?php

declare(strict_types=1);

namespace App\Modules\Platform\UI\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class UiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'ui');

        Blade::componentNamespace('App\\Modules\\Platform\\UI\\View\\Components', 'ui');
    }
}
