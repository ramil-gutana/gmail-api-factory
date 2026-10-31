<?php

namespace App\Shared\Gmail\Facade;

use App\Shared\Gmail\GmailManager;
use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static GmailServiceInterface using(string $account)
 *
 * @see GmailManager
 */
class Gmail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gmail.manager';
    }
}
