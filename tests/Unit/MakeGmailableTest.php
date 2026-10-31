<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeGmailableTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->basePath('app/Mail/Gmail'));
        File::deleteDirectory($this->app->resourcePath('views/mail/gmail'));
        File::deleteDirectory($this->app->resourcePath('views/emails'));
        File::deleteDirectory($this->app->basePath('stubs'));

        parent::tearDown();
    }

    private function classPath(string $name): string
    {
        return $this->app->basePath("app/Mail/Gmail/{$name}.php");
    }

    public function test_it_generates_a_mailable_class(): void
    {
        $this->artisan('gmail-api:make-mailable', ['name' => 'WelcomeMail'])->assertSuccessful();

        $class = file_get_contents($this->classPath('WelcomeMail'));

        $this->assertStringContainsString('namespace App\Mail\Gmail;', $class);
        $this->assertStringContainsString('class WelcomeMail extends GMailable', $class);
        $this->assertStringContainsString("parent::__construct(subject: 'Welcome Mail');", $class);
        $this->assertStringContainsString("\$this->html('<p>Welcome Mail</p>');", $class);
        $this->assertDirectoryDoesNotExist($this->app->resourcePath('views/mail/gmail'));
    }

    public function test_view_option_creates_a_blade_view_with_the_default_name(): void
    {
        $this->artisan('gmail-api:make-mailable', ['name' => 'Billing/InvoicePaid', '--view' => null])->assertSuccessful();

        $class = file_get_contents($this->classPath('Billing/InvoicePaid'));

        $this->assertStringContainsString('namespace App\Mail\Gmail\Billing;', $class);
        $this->assertStringContainsString("\$this->view('mail.gmail.billing.invoice-paid');", $class);
        $this->assertStringContainsString(
            '<p>Invoice Paid</p>',
            file_get_contents($this->app->resourcePath('views/mail/gmail/billing/invoice-paid.blade.php'))
        );
    }

    public function test_view_option_accepts_a_custom_view_name(): void
    {
        $this->artisan('gmail-api:make-mailable', ['name' => 'OrderShipped', '--view' => 'emails.orders.shipped'])->assertSuccessful();

        $this->assertStringContainsString("\$this->view('emails.orders.shipped');", file_get_contents($this->classPath('OrderShipped')));
        $this->assertFileExists($this->app->resourcePath('views/emails/orders/shipped.blade.php'));
    }

    public function test_it_does_not_overwrite_without_force(): void
    {
        $this->artisan('gmail-api:make-mailable', ['name' => 'WelcomeMail'])->assertSuccessful();
        file_put_contents($this->classPath('WelcomeMail'), '<?php // edited');

        $this->artisan('gmail-api:make-mailable', ['name' => 'WelcomeMail'])->assertFailed();
        $this->assertSame('<?php // edited', file_get_contents($this->classPath('WelcomeMail')));

        $this->artisan('gmail-api:make-mailable', ['name' => 'WelcomeMail', '--force' => true])->assertSuccessful();
        $this->assertStringContainsString('class WelcomeMail', file_get_contents($this->classPath('WelcomeMail')));
    }

    public function test_published_stubs_take_precedence(): void
    {
        File::ensureDirectoryExists($this->app->basePath('stubs'));
        file_put_contents($this->app->basePath('stubs/gmailable.stub'), "<?php\n\nnamespace {{ namespace }};\n\n// custom {{ class }}: {{ subject }}\n");

        $this->artisan('gmail-api:make-mailable', ['name' => 'WelcomeMail'])->assertSuccessful();

        $this->assertStringContainsString('// custom WelcomeMail: Welcome Mail', file_get_contents($this->classPath('WelcomeMail')));
    }

    public function test_the_generated_class_renders_a_message(): void
    {
        config()->set('mail.from', ['address' => 'app@example.com', 'name' => 'Example App']);

        $this->artisan('gmail-api:make-mailable', ['name' => 'GeneratedRenderCheck'])->assertSuccessful();
        require $this->classPath('GeneratedRenderCheck');

        $raw = (new \App\Mail\Gmail\GeneratedRenderCheck)->to('bob@example.com')->render();

        $this->assertStringContainsString('From: Example App <app@example.com>', $raw);
        $this->assertStringContainsString('Subject: Generated Render Check', $raw);
        $this->assertStringContainsString('<p>Generated Render Check</p>', quoted_printable_decode($raw));
    }
}
