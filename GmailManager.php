<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Exceptions\AccountNotConfiguredException;
use App\Shared\Gmail\Exceptions\GmailException;
use App\Shared\Gmail\Factory\GoogleClientFactory;
use Google\Exception;

/**
 * @mixin GmailService
 */
class GmailManager
{
    /**
     * @var array<string, GmailService>
     */
    protected array $services = [];

    /**
     * Get the service for an account, or the default account when none is given.
     * Services are created once per account and reused.
     *
     * @throws AccountNotConfiguredException
     * @throws GmailException
     * @throws Exception
     */
    public function using(?string $account = null): GmailService
    {
        $account ??= $this->getDefaultAccount();

        return $this->services[$account] ??= $this->make($account);
    }

    /**
     * Send a mailable through its own account (GMailable::account()), or the default account.
     *
     * @throws AccountNotConfiguredException
     * @throws GmailException
     * @throws \Throwable
     */
    public function send(GMailable $mailable): string
    {
        return $this->using($mailable->account)->send($mailable);
    }

    public function getDefaultAccount(): string
    {
        return config('gmail.default', 'default');
    }

    /**
     * Drop cached services so the next using() call rebuilds the client (e.g. after re-authorising).
     */
    public function forget(?string $account = null): static
    {
        if ($account === null) {
            $this->services = [];
        } else {
            unset($this->services[$account]);
        }

        return $this;
    }

    /**
     * @throws AccountNotConfiguredException
     * @throws GmailException
     * @throws Exception
     */
    protected function make(string $account): GmailService
    {
        $config = config("gmail.accounts.{$account}");

        if (! $config) {
            throw new AccountNotConfiguredException($account);
        }

        return new GmailService(GoogleClientFactory::make($config + ['name' => $account]), account: $account);
    }

    /**
     * Forward calls to the default account, e.g. Gmail::fetchMessages().
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->using()->{$method}(...$parameters);
    }
}
