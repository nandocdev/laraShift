<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();

        \Illuminate\Database\Eloquent\Factories\Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'App\\Modules\\')) {
                return str_replace('\\Models\\', '\\Database\\Factories\\', $modelName) . 'Factory';
            }
            return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });

        \Illuminate\Support\Facades\Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        \Livewire\Livewire::setUpdateRoute(function ($handle) {
            $path = class_exists(\Livewire\Mechanisms\HandleRequests\EndpointResolver::class) 
                ? \Livewire\Mechanisms\HandleRequests\EndpointResolver::updatePath() 
                : '/livewire/update';

            return \Illuminate\Support\Facades\Route::post($path, $handle)
                ->middleware([
                    'web',
                    'universal',
                    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                ]);
        });
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
