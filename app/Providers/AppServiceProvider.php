<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use App\Modules\Tenant\Experience\Domain\Policies\TenantSettingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::policy(
            TenantSetting::class,
            TenantSettingPolicy::class
        );

        $this->configureDefaults();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'App\\Modules\\')) {
                return str_replace('\\Models\\', '\\Database\\Factories\\', $modelName).'Factory';
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        Livewire::setUpdateRoute(function ($handle) {
            $path = class_exists(EndpointResolver::class)
                ? EndpointResolver::updatePath()
                : '/livewire/update';

            return Route::post($path, $handle)
                ->middleware([
                    'web',
                    'universal',
                    InitializeTenancyByDomain::class,
                ]);
        });

        // Inject tenancy middleware into file preview route so it resolves the tenant disk.
        FilePreviewController::$middleware = [
            'web',
            'universal',
            InitializeTenancyByDomain::class,
        ];
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
