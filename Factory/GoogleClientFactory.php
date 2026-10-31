<?php

namespace App\Shared\Gmail\Factory;

use Google\Client;
use Google\Exception;

class GoogleClientFactory
{
    /**
     * @throws Exception|\Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function make(array $config)
    {
        $client = new Client;

        $client->setAuthConfig($config['credentials']);
        $client->setScopes($config['scopes']);
        $client->setAccessType('offline');
        $client->setPrompt('none');
        $client->setAccessToken(json_decode(file_get_contents($config['token']), true));

        if ($client->isAccessTokenExpired()) {
            \Artisan::call("gmail-api:access-token {$config['name']}");
            $client->setAccessToken(json_decode(file_get_contents($config['token']), true));
        }

        return $client;
    }
}
