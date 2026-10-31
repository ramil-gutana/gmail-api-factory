<?php

namespace Tests\Unit;

use Google\Client;
use Mockery;
use Tests\TestCase;

class GenerateGmailAccessTokenTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/gmail-command-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/credentials.json', json_encode([
            'installed' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect_uris' => ['http://localhost']],
        ]));

        config()->set('gmail.accounts.support', [
            'credentials' => $this->dir.'/credentials.json',
            'token' => $this->dir.'/token.json',
            'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
        ]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function mockClient(): Mockery\MockInterface
    {
        $client = Mockery::mock(Client::class, [[]])->makePartial();
        $this->app->instance(Client::class, $client);

        return $client;
    }

    private function savedToken(): array
    {
        return json_decode(file_get_contents($this->dir.'/token.json'), true);
    }

    public function test_it_fails_for_an_unknown_account(): void
    {
        $this->artisan('gmail-api:access-token', ['account' => 'nope'])
            ->expectsOutput('Gmail account [nope] is not configured.')
            ->assertFailed();
    }

    public function test_it_creates_a_token_from_the_verification_code(): void
    {
        $this->mockClient()->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('code-123')
            ->andReturn(['access_token' => 'new', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'created' => time()]);

        $this->artisan('gmail-api:access-token', ['account' => 'support'])
            ->expectsQuestion('Enter verification code:', 'code-123')
            ->expectsOutput('Token created for account [support].')
            ->assertSuccessful();

        $this->assertSame('new', $this->savedToken()['access_token']);
    }

    public function test_it_fails_when_google_rejects_the_verification_code(): void
    {
        $this->mockClient()->shouldReceive('fetchAccessTokenWithAuthCode')->andReturn(['error' => 'invalid_grant']);

        $this->artisan('gmail-api:access-token', ['account' => 'support'])
            ->expectsQuestion('Enter verification code:', 'bad')
            ->expectsOutput('Unable to create Gmail access token for [support]: invalid_grant')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->dir.'/token.json');
    }

    public function test_it_leaves_a_valid_token_alone(): void
    {
        file_put_contents($this->dir.'/token.json', json_encode(['access_token' => 'abc', 'expires_in' => 3600, 'created' => time()]));

        $this->artisan('gmail-api:access-token', ['account' => 'support'])
            ->expectsOutput('Token for account [support] has not expired.')
            ->assertSuccessful();
    }

    public function test_it_refreshes_an_expired_token(): void
    {
        file_put_contents($this->dir.'/token.json', json_encode([
            'access_token' => 'old', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'created' => time() - 7200,
        ]));

        $this->mockClient()->shouldReceive('fetchAccessTokenWithRefreshToken')->once()->with('refresh')
            ->andReturn(['access_token' => 'new', 'expires_in' => 3600, 'created' => time()]);

        $this->artisan('gmail-api:access-token', ['account' => 'support'])
            ->expectsOutput('Token for account [support] refreshed.')
            ->assertSuccessful();

        $this->assertSame('new', $this->savedToken()['access_token']);
        $this->assertSame('refresh', $this->savedToken()['refresh_token']);
    }
}
