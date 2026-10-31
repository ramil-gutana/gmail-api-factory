<?php

namespace Tests\Unit;

use Tests\TestCase;

class GenerateGmailAccessTokenTest extends TestCase
{
    public function test_it_fails_for_an_unknown_account(): void
    {
        $this->artisan('gmail-api:access-token', ['account' => 'nope'])
            ->expectsOutput('Gmail config for account : nope not found')
            ->assertFailed();
    }
}
