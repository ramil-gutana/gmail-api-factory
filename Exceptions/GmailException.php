<?php

namespace App\Shared\Gmail\Exceptions;

use RuntimeException;

class GmailException extends RuntimeException
{
    public static function tokenMissing(string $account, string $path): static
    {
        return new static("Gmail token file [{$path}] not found. Run `php artisan gmail-api:access-token {$account}` first.");
    }

    public static function missingSender(): static
    {
        return new static('GMailable has no sender: pass $from or set mail.from.address.');
    }

    public static function tokenInvalid(string $path): static
    {
        return new static("Gmail token file [{$path}] does not contain valid JSON.");
    }

    public static function tokenNotRefreshable(string $account): static
    {
        return new static("Gmail access token for [{$account}] expired and has no refresh token. Run `php artisan gmail-api:access-token {$account}`.");
    }

    public static function authorizationFailed(string $account, array $response): static
    {
        return new static("Unable to create Gmail access token for [{$account}]: ".($response['error_description'] ?? $response['error']));
    }

    public static function refreshFailed(string $account, array $response): static
    {
        return new static("Unable to refresh Gmail access token for [{$account}]: ".($response['error_description'] ?? $response['error']));
    }
}
