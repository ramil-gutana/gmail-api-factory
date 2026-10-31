<?php

namespace App\Shared\Gmail\Commands;

use App\Shared\Gmail\Exceptions\AccountNotConfiguredException;
use App\Shared\Gmail\Exceptions\GmailException;
use App\Shared\Gmail\Factory\GoogleClientFactory;
use Google\Client;
use Google\Exception;
use Illuminate\Console\Command;

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
        $config = config("gmail.accounts.{$account}");

        if (empty($config)) {
            $this->error((new AccountNotConfiguredException($account))->getMessage());

            return self::FAILURE;
        }

        $config += ['name' => $account];
        $client = GoogleClientFactory::configure($config, $this->laravel->make(Client::class), 'select_account consent');

        try {
            $token = GoogleClientFactory::readToken($config['token']);

            if ($token === null) {
                $this->authorize($client, $config);
                $this->info("Token created for account [{$account}].");

                return self::SUCCESS;
            }

            $client->setAccessToken($token);

            if (! $client->isAccessTokenExpired()) {
                $this->info("Token for account [{$account}] has not expired.");

                return self::SUCCESS;
            }

            GoogleClientFactory::refresh($client, $config);
            $this->info("Token for account [{$account}] refreshed.");

            return self::SUCCESS;
        } catch (GmailException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Run the interactive OAuth consent flow and save the resulting token.
     *
     * @throws GmailException
     */
    protected function authorize(Client $client, array $config): void
    {
        $this->info('Open the following link in your browser: '.$client->createAuthUrl());

        $response = $client->fetchAccessTokenWithAuthCode($this->ask('Enter verification code:'));

        if (isset($response['error'])) {
            throw GmailException::authorizationFailed($config['name'], $response);
        }

        $client->setAccessToken($response);
        GoogleClientFactory::saveToken($config['token'], $client->getAccessToken());
    }
}
