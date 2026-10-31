<?php

namespace App\Shared\Gmail\Exceptions;

use InvalidArgumentException;

class AccountNotConfiguredException extends InvalidArgumentException
{
    public function __construct(public readonly string $account)
    {
        parent::__construct("Gmail account [{$account}] is not configured.");
    }
}
