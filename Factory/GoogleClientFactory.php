<?php

namespace App\Shared\Gmail\Factory;

use Google\Client;
use Google\Exception;
use RuntimeException;

class GoogleClientFactory
{
    /**
     * @throws Exception
     * @throws RuntimeException when the token file is missing or the token cannot be refreshed
     */
    public static function make(array $config, ?Client $client = null): Client
    {
        $client ??= new Client;
        $name = $config['name'] ?? 'default';

        $client->setAuthConfig($config['credentials']);
        $client->setScopes($config['scopes']);
        $client->setAccessType('offline');
        $client->setPrompt('none');

        if (! is_file($config['token'])) {
            throw new RuntimeException(
                "Gmail token file [{$config['token']}] not found. Run `php artisan gmail-api:access-token {$name}` first."
            );
        }

        $token = json_decode(file_get_contents($config['token']), true);

        if (! is_array($token)) {
            throw new RuntimeException("Gmail token file [{$config['token']}] does not contain valid JSON.");
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();

            if (! $refreshToken) {
                throw new RuntimeException(
                    "Gmail access token for [{$name}] expired and has no refresh token. Run `php artisan gmail-api:access-token {$name}`."
                );
            }

            $refreshed = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($refreshed['error'])) {
                throw new RuntimeException(
                    "Unable to refresh Gmail access token for [{$name}]: ".($refreshed['error_description'] ?? $refreshed['error'])
                );
            }

            $token = array_merge($token, $refreshed);
            $client->setAccessToken($token);
            file_put_contents($config['token'], json_encode($token, JSON_PRETTY_PRINT));
        }

        return $client;
    }
}
