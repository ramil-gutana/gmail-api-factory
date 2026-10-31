<?php

namespace Tests\Unit;

use App\Shared\Gmail\Facade\Gmail;
use InvalidArgumentException;
use Tests\TestCase;

class GmailManagerTest extends TestCase
{
    public function test_unknown_account_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gmail account [nope] is not configured.');

        Gmail::using('nope');
    }
}
