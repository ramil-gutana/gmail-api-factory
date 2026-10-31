<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Commands\GenerateGmailAccessToken;
use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use Illuminate\Support\ServiceProvider;

class GmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            GenerateGmailAccessToken::class,
        ]);

        $this->app->bind(GmailServiceInterface::class, GmailService::class);

        $this->app->singleton('gmail.manager', function () {
            return new GmailManager;
        });
    }

    public function boot(): void {}
}
