<?php

namespace App\Shared\Gmail\Commands;

use Google\Client;
use Google\Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class GenerateGmailAccessToken extends Command
{
    protected $signature = 'gmail-api:access-token {account}';

    protected $description = 'Generate or refresh the OAuth access token of a configured Gmail account';

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        $account = $this->argument('account');
        $config = config("gmail.accounts.{$account}", null);

        if (empty($config)) {
            $this->error("Gmail config for account : {$account} not found");

            return self::FAILURE;
        }

        $client = new Client;
        $client->setAuthConfig($config['credentials']);
        $client->setScopes($config['scopes']);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $accessToken = [];
        if (file_exists($config['token'])) {
            $accessToken = json_decode(file_get_contents($config['token']), true);
            $client->setAccessToken($accessToken);
        }

        if (empty($accessToken)) {
            $authUrl = $client->createAuthUrl();
            $this->info("Open the following link in your browser: $authUrl");
            $verificationCode = $this->ask('Enter verification code:');
            $accessToken = $client->fetchAccessTokenWithAuthCode($verificationCode);

            if (Arr::has($accessToken, 'error')) {
                $this->error("Error: cannot generate token {$accessToken['error']}");

                return self::FAILURE;
            }

            $client->setAccessToken($accessToken);
            File::ensureDirectoryExists(dirname($config['token']));
            File::put($config['token'], json_encode($client->getAccessToken(), JSON_PRETTY_PRINT));
        } else {
            if ($client->isAccessTokenExpired()) {
                if (! $client->getRefreshToken()) {
                    $this->error("The token of {$account} expired and has no refresh token. Delete {$config['token']} and run this command again.");

                    return self::FAILURE;
                }

                $refreshToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                if (Arr::has($refreshToken, 'error')) {
                    $this->error("Error: cannot refresh token {$refreshToken['error']}");

                    return self::FAILURE;
                }

                $newAccessToken = array_merge($accessToken, $refreshToken);
                $client->setAccessToken($newAccessToken);
                File::put($config['token'], json_encode($newAccessToken, JSON_PRETTY_PRINT));
                $this->info("An Refresh Token for account {$account} successfully generated");

                return self::SUCCESS;
            }
            $this->info("The token of {$account} have not been expired");

            return self::SUCCESS;
        }

        $this->info("Token created for account {$account}");

        return self::SUCCESS;
    }
}
