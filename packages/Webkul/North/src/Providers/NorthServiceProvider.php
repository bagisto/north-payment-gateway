<?php

namespace Webkul\North\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webkul\North\Console\Commands\InstallNorth;

class NorthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->registerCommands();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'north');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'north');

        View::prependNamespace('shop', __DIR__.'/../Resources/views/shop');

        $this->publishes([
            __DIR__.'/../Resources/assets/images' => storage_path('app/public/north'),
        ], 'public');

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Merge North's configuration with the payment methods config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/payment-methods.php', 'payment_methods'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php', 'core'
        );
    }

    /**
     * Register the console commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallNorth::class,
            ]);
        }
    }
}
