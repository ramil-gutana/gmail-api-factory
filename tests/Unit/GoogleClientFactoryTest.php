<?php

namespace Tests\Unit;

use App\Shared\Gmail\Exceptions\GmailException;
use App\Shared\Gmail\Factory\GoogleClientFactory;
use Google\Client;
use Mockery;
use Tests\TestCase;

class GoogleClientFactoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/gmail-factory-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/credentials.json', json_encode([
            'installed' => [
                'client_id' => 'client-id',
                'client_secret' => 'secret',
                'redirect_uris' => ['urn:ietf:wg:oauth:2.0:oob'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function config(): array
    {
        return [
            'name' => 'support',
            'credentials' => $this->dir.'/credentials.json',
            'token' => $this->dir.'/token.json',
            'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
        ];
    }

    private function writeToken(array $token): void
    {
        file_put_contents($this->dir.'/token.json', json_encode($token));
    }

    public function test_it_builds_a_client_with_a_valid_token(): void
    {
        $this->writeToken(['access_token' => 'abc', 'expires_in' => 3600, 'created' => time()]);

        $client = GoogleClientFactory::make($this->config());

        $this->assertSame('abc', $client->getAccessToken()['access_token']);
        $this->assertSame('client-id', $client->getClientId());
        $this->assertSame(['https://www.googleapis.com/auth/gmail.modify'], $client->getScopes());
    }

    public function test_missing_token_file_throws_a_helpful_error(): void
    {
        $this->expectException(GmailException::class);
        $this->expectExceptionMessage('php artisan gmail-api:access-token support');

        GoogleClientFactory::make($this->config());
    }

    public function test_expired_token_is_refreshed_and_saved(): void
    {
        $this->writeToken(['access_token' => 'old', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'created' => time() - 7200]);

        $client = Mockery::mock(Client::class)->makePartial();
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->with('refresh')
            ->andReturn(['access_token' => 'new', 'expires_in' => 3600, 'created' => time()]);

        GoogleClientFactory::make($this->config(), $client);

        $saved = json_decode(file_get_contents($this->dir.'/token.json'), true);
        $this->assertSame('new', $saved['access_token']);
        $this->assertSame('refresh', $saved['refresh_token']);
    }

    public function test_failed_refresh_throws(): void
    {
        $this->writeToken(['access_token' => 'old', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'created' => time() - 7200]);

        $client = Mockery::mock(Client::class)->makePartial();
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')->andReturn(['error' => 'invalid_grant']);

        $this->expectException(GmailException::class);
        $this->expectExceptionMessage('invalid_grant');

        GoogleClientFactory::make($this->config(), $client);
    }

    public function test_invalid_token_file_throws(): void
    {
        file_put_contents($this->dir.'/token.json', 'not json');

        $this->expectException(GmailException::class);
        $this->expectExceptionMessage('does not contain valid JSON');

        GoogleClientFactory::make($this->config());
    }

    public function test_expired_token_without_refresh_token_throws(): void
    {
        $this->writeToken(['access_token' => 'old', 'expires_in' => 3600, 'created' => time() - 7200]);

        $this->expectException(GmailException::class);
        $this->expectExceptionMessage('has no refresh token');

        GoogleClientFactory::make($this->config());
    }

    public function test_save_token_creates_missing_directories(): void
    {
        $path = $this->dir.'/nested/token.json';

        GoogleClientFactory::saveToken($path, ['access_token' => 'abc']);

        $this->assertSame(['access_token' => 'abc'], GoogleClientFactory::readToken($path));
        unlink($path);
        rmdir(dirname($path));
    }
}
