<?php

namespace App\Shared\Gmail\Factory;

use App\Shared\Gmail\Exceptions\GmailException;
use Google\Client;
use Google\Exception;

class GoogleClientFactory
{
    /**
     * Build an authenticated client for an account config, refreshing and saving an expired token.
     *
     * @throws Exception when the credentials file cannot be read
     * @throws GmailException when the token is missing, invalid or cannot be refreshed
     */
    public static function make(array $config, ?Client $client = null): Client
    {
        $client = static::configure($config, $client);
        $account = static::accountName($config);
        $token = static::readToken($config['token']);

        if ($token === null) {
            throw GmailException::tokenMissing($account, $config['token']);
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            static::refresh($client, $config);
        }

        return $client;
    }

    /**
     * Apply the OAuth client credentials and scopes of an account config.
     *
     * @throws Exception
     */
    public static function configure(array $config, ?Client $client = null, string $prompt = 'none'): Client
    {
        $client ??= new Client;

        $client->setAuthConfig($config['credentials']);
        $client->setScopes($config['scopes']);
        $client->setAccessType('offline');
        $client->setPrompt($prompt);

        return $client;
    }

    /**
     * Refresh the client's expired access token and persist it to the account's token file.
     *
     * @throws GmailException
     */
    public static function refresh(Client $client, array $config): array
    {
        $account = static::accountName($config);
        $refreshToken = $client->getRefreshToken();

        if (! $refreshToken) {
            throw GmailException::tokenNotRefreshable($account);
        }

        $response = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($response['error'])) {
            throw GmailException::refreshFailed($account, $response);
        }

        $token = array_merge($client->getAccessToken(), $response);
        $client->setAccessToken($token);
        static::saveToken($config['token'], $token);

        return $token;
    }

    /**
     * @throws GmailException when the file exists but is not a JSON token
     */
    public static function readToken(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $token = json_decode(file_get_contents($path), true);

        if (! is_array($token)) {
            throw GmailException::tokenInvalid($path);
        }

        return $token;
    }

    public static function saveToken(string $path, array $token): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($token, JSON_PRETTY_PRINT));
    }

    protected static function accountName(array $config): string
    {
        return $config['name'] ?? 'default';
    }
}
