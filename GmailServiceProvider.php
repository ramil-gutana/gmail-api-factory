<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Commands\GenerateGmailAccessToken;
use App\Shared\Gmail\Commands\MakeGmailable;
use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use Illuminate\Support\ServiceProvider;

class GmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/gmail.php', 'gmail');

        $this->commands([
            GenerateGmailAccessToken::class,
            MakeGmailable::class,
        ]);

        $this->app->singleton(GmailManager::class);
        $this->app->alias(GmailManager::class, 'gmail.manager');

        $this->app->bind(GmailServiceInterface::class, fn ($app) => $app->make(GmailManager::class)->using());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/gmail.php' => config_path('gmail.php'),
            ], 'gmail-config');

            $this->publishes([
                __DIR__.'/stubs/gmailable.stub' => base_path('stubs/gmailable.stub'),
                __DIR__.'/stubs/gmailable-view.stub' => base_path('stubs/gmailable-view.stub'),
                __DIR__.'/stubs/gmailable-view.blade.stub' => base_path('stubs/gmailable-view.blade.stub'),
            ], 'gmail-stubs');
        }
    }
}
