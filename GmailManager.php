<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Factory\GoogleClientFactory;
use Google\Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class GmailManager
{
    /**
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function using(string $account): GmailService
    {

        $config = config("gmail.accounts.{$account}");

        if (! $config) {
            throw new \InvalidArgumentException("Gmail account [$account] is not configured.");
        }

        $client = GoogleClientFactory::make($config + ['name' => $account]);

        return new GmailService($client);
    }
}
