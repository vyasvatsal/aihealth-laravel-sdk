<?php

namespace AIHealth\Laravel;

use Illuminate\Support\ServiceProvider;

class AIHealthServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/aihealth.php', 'aihealth');

        $this->app->singleton(Client::class, function ($app) {
            return new Client(config('aihealth'), $app);
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole() && function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/config/aihealth.php' => config_path('aihealth.php'),
            ], 'aihealth-config');
        }

        // Only register hooks if the DSN is configured
        if (config('aihealth.dsn')) {
            if (config('aihealth.send_exceptions')) {
                $this->app->make(ErrorHandler::class)->register();
            }

            if (config('aihealth.send_logs')) {
                $this->app->make(LogHandler::class)->register();
            }
        }
    }
}
