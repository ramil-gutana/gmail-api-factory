<?php

namespace Tests\Unit;

use App\Shared\Gmail\Facade\Gmail;
use App\Shared\Gmail\GmailManager;
use App\Shared\Gmail\GmailService;
use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use App\Shared\Gmail\Exceptions\AccountNotConfiguredException;
use InvalidArgumentException;
use Tests\TestCase;

class GmailManagerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/gmail-manager-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/credentials.json', json_encode([
            'installed' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect_uris' => ['http://localhost']],
        ]));

        foreach (['support', 'sales'] as $account) {
            file_put_contents($this->dir."/$account.json", json_encode([
                'access_token' => "$account-token", 'expires_in' => 3600, 'created' => time(),
            ]));

            config()->set("gmail.accounts.$account", [
                'credentials' => $this->dir.'/credentials.json',
                'token' => $this->dir."/$account.json",
                'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
            ]);
        }

        config()->set('gmail.default', 'support');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function token(GmailService $service): string
    {
        return (fn () => $this->client->getAccessToken()['access_token'])->call($service);
    }

    public function test_package_config_is_merged(): void
    {
        $this->assertArrayHasKey('default', config('gmail.accounts'));
    }

    public function test_unknown_account_throws(): void
    {
        try {
            Gmail::using('nope');
            $this->fail('Expected an exception.');
        } catch (AccountNotConfiguredException $e) {
            // Still an InvalidArgumentException, so existing catch blocks keep working.
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertSame('nope', $e->account);
            $this->assertSame('Gmail account [nope] is not configured.', $e->getMessage());
        }
    }

    public function test_it_resolves_named_and_default_accounts(): void
    {
        $this->assertSame('sales-token', $this->token(Gmail::using('sales')));
        $this->assertSame('support-token', $this->token(Gmail::using()));
        $this->assertSame('support', Gmail::getDefaultAccount());
    }

    public function test_services_are_cached_per_account_until_forgotten(): void
    {
        $manager = $this->app->make(GmailManager::class);
        $service = $manager->using('sales');

        $this->assertSame($service, $manager->using('sales'));
        $this->assertNotSame($service, $manager->using('support'));
        $this->assertNotSame($service, $manager->forget('sales')->using('sales'));
    }

    public function test_the_interface_resolves_to_the_default_account(): void
    {
        $service = $this->app->make(GmailServiceInterface::class);

        $this->assertSame($this->app->make('gmail.manager')->using('support'), $service);
    }

    public function test_calls_are_forwarded_to_the_default_account(): void
    {
        $manager = new class extends GmailManager
        {
            public array $calls = [];

            public function using(?string $account = null): GmailService
            {
                $manager = $this;

                return new class($manager) extends GmailService
                {
                    public function __construct(private $manager) {}

                    public function markAsRead(string $messageId): bool
                    {
                        $this->manager->calls[] = $messageId;

                        return true;
                    }
                };
            }
        };

        $this->assertTrue($manager->markAsRead('m1'));
        $this->assertSame(['m1'], $manager->calls);
    }
}
