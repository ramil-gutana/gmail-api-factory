<?php

namespace Tests;

use App\Shared\Gmail\GmailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GmailServiceProvider::class];
    }
}
