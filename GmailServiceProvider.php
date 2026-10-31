<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Commands\GenerateGmailAccessToken;
use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use Illuminate\Support\ServiceProvider;

class GmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/gmail.php', 'gmail');

        $this->commands([
            GenerateGmailAccessToken::class,
        ]);

        $this->app->singleton('gmail.manager', function () {
            return new GmailManager;
        });

        $this->app->alias('gmail.manager', GmailManager::class);

        $this->app->bind(GmailServiceInterface::class, fn ($app) => $app['gmail.manager']->using());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/gmail.php' => config_path('gmail.php'),
            ], 'gmail-config');
        }
    }
}
