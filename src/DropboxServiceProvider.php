<?php

namespace Dcblogdev\Dropbox;

use Illuminate\Support\ServiceProvider;
use Dcblogdev\Dropbox\DropboxAuthenticated;

class DropboxServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(\Illuminate\Routing\Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {

            // Publishing the configuration file.
            $this->publishes([
                __DIR__.'/../config/dropbox.php' => config_path('dropbox.php'),
            ], 'config');

            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/database/migrations/create_dropbox_tokens_table.php' => $this->app->databasePath()."/migrations/{$timestamp}_create_dropbox_tokens_table.php",
            ], 'migrations');            
        }

        //add middleware
        $router->aliasMiddleware('DropboxAuthenticated', DropboxAuthenticated::class);
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dropbox.php', 'dropbox');

        // Register the service the package provides.
        $this->app->singleton('dropbox', function ($app) {
            return new Dropbox;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['dropbox'];
    }
}
