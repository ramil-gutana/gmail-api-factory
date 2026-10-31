<?php

namespace Tests\Unit;

use App\Shared\Gmail\Exceptions\GmailException;
use App\Shared\Gmail\GMailable;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class GMailableTest extends TestCase
{
    private function mail(): GMailable
    {
        return (new GMailable('Support <support@example.com>', 'Hello'))->to('bob@example.com');
    }

    /**
     * Parse "Name: value" headers of the top-level message, unfolding continuation lines.
     */
    private function headers(string $raw): array
    {
        $head = preg_replace("/\r\n[ \t]+/", ' ', explode("\r\n\r\n", $raw, 2)[0]);
        $headers = [];
        foreach (explode("\r\n", $head) as $line) {
            [$name, $value] = explode(': ', $line, 2);
            $headers[$name] = $value;
        }

        return $headers;
    }

    public function test_it_renders_a_plain_text_message(): void
    {
        $raw = $this->mail()->cc(['carol@example.com', 'dave@example.com'])->plain('Hi Bob')->render();
        $headers = $this->headers($raw);

        $this->assertSame('Support <support@example.com>', $headers['From']);
        $this->assertSame('bob@example.com', $headers['To']);
        $this->assertSame('carol@example.com, dave@example.com', $headers['Cc']);
        $this->assertSame('Hello', $headers['Subject']);
        $this->assertSame('1.0', $headers['MIME-Version']);
        $this->assertStringStartsWith('text/plain; charset=utf-8', $headers['Content-Type']);
        $this->assertStringEndsWith("\r\n\r\nHi Bob", $raw);
    }

    public function test_html_is_the_default_mode(): void
    {
        $headers = $this->headers($this->mail()->html('<b>Hi</b>')->render());

        $this->assertStringStartsWith('text/html; charset=utf-8', $headers['Content-Type']);
    }

    public function test_bcc_is_kept_in_the_raw_message_for_gmail(): void
    {
        $headers = $this->headers($this->mail()->bcc(['x@example.com', 'y@example.com'])->plain('Hi')->render());

        $this->assertSame('x@example.com, y@example.com', $headers['Bcc']);
    }

    public function test_bcc_only_messages_are_valid(): void
    {
        $raw = (new GMailable('a@example.com', 'Hi'))->bcc('x@example.com')->plain('Hi')->render();

        $this->assertArrayNotHasKey('To', $this->headers($raw));
    }

    public function test_non_ascii_subjects_are_encoded(): void
    {
        $headers = $this->headers($this->mail()->subject('Café ☕ menu')->plain('Hi')->render());

        $this->assertMatchesRegularExpression('/^=\?utf-8\?/i', $headers['Subject']);
        $this->assertSame('Café ☕ menu', iconv_mime_decode($headers['Subject'], 0, 'UTF-8'));
    }

    public function test_header_injection_is_not_possible(): void
    {
        $raw = $this->mail()->subject("Hi\r\nBcc: evil@example.com")->plain('Hi')->render();

        $this->assertArrayNotHasKey('Bcc', $this->headers($raw));
    }

    public function test_alt_text_creates_a_multipart_alternative_message(): void
    {
        $raw = $this->mail()->html('<b>Hi</b>')->altText('Hi')->render();

        $this->assertStringStartsWith('multipart/alternative', $this->headers($raw)['Content-Type']);
        $this->assertStringContainsString("Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nHi", $raw);
        $this->assertStringContainsString('<b>Hi</b>', $raw);
    }

    public function test_attachments_create_a_multipart_mixed_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, 'file contents');

        try {
            $raw = $this->mail()
                ->plain('See attached')
                ->attach($path, 'report.txt', 'text/plain')
                ->attachData('a,b', 'data.csv', 'text/csv')
                ->render();
        } finally {
            unlink($path);
        }

        $this->assertStringStartsWith('multipart/mixed', $this->headers($raw)['Content-Type']);
        $this->assertStringContainsString('filename=report.txt', $raw);
        $this->assertStringContainsString(base64_encode('file contents'), $raw);
        $this->assertStringContainsString('filename=data.csv', $raw);
    }

    public function test_reply_headers_and_thread(): void
    {
        $mail = $this->mail()->replyTo('help@example.com')->inReplyTo('<abc@mail.gmail.com>', 'thread-1')->plain('Re');
        $headers = $this->headers($mail->render());

        $this->assertSame('help@example.com', $headers['Reply-To']);
        $this->assertSame('<abc@mail.gmail.com>', $headers['In-Reply-To']);
        $this->assertSame('<abc@mail.gmail.com>', $headers['References']);
        $this->assertSame('thread-1', $mail->threadId);
    }

    public function test_it_renders_blade_views(): void
    {
        View::addNamespace('gmail-test', dirname(__DIR__).'/Fixtures/views');

        $raw = $this->mail()->view('gmail-test::welcome', ['name' => 'Bob'])->render();

        $this->assertStringContainsString('<h1>Hello Bob</h1>', quoted_printable_decode($raw));
    }

    public function test_build_hook_runs_once_before_rendering(): void
    {
        $mail = new class('a@example.com', 'Hi') extends GMailable
        {
            public int $builds = 0;

            protected function build(): void
            {
                $this->builds++;
                $this->to('bob@example.com')->plain('Built')->attachData('x', 'x.txt', 'text/plain');
            }
        };

        $first = $mail->render();
        $second = $mail->render();

        $this->assertSame(1, $mail->builds);
        $this->assertCount(1, $mail->attachments);
        $this->assertStringContainsString('Built', $first);
        $this->assertSame(substr_count($first, 'filename=x.txt'), substr_count($second, 'filename=x.txt'));
    }

    public function test_sender_defaults_to_the_mail_from_config(): void
    {
        config()->set('mail.from', ['address' => 'app@example.com', 'name' => 'Example App']);

        $headers = $this->headers((new GMailable(subject: 'Hi'))->to('bob@example.com')->plain('Hi')->render());

        $this->assertSame('Example App <app@example.com>', $headers['From']);
    }

    public function test_from_setter_overrides_the_default_sender(): void
    {
        $headers = $this->headers((new GMailable)->from('me@example.com')->to('bob@example.com')->plain('Hi')->render());

        $this->assertSame('me@example.com', $headers['From']);
    }

    public function test_missing_sender_throws(): void
    {
        config()->set('mail.from.address', null);

        $this->expectException(GmailException::class);
        $this->expectExceptionMessage('GMailable has no sender');

        (new GMailable)->to('bob@example.com')->plain('Hi')->render();
    }

    public function test_account_option_is_stored(): void
    {
        $mail = (new GMailable('a@example.com'))->account('sales');

        $this->assertSame('sales', $mail->account);
        $this->assertNull($mail->account(null)->account);
    }

    public function test_sender_defaults_to_the_accounts_from_config(): void
    {
        config()->set('mail.from', ['address' => 'app@example.com', 'name' => 'Example App']);
        config()->set('gmail.accounts.sales.from', 'Sales <sales@example.com>');

        $sales = $this->headers((new GMailable)->account('sales')->to('bob@example.com')->plain('Hi')->render());
        $explicit = $this->headers((new GMailable('me@example.com'))->account('sales')->to('bob@example.com')->plain('Hi')->render());
        $noAccountFrom = $this->headers((new GMailable)->account('other')->to('bob@example.com')->plain('Hi')->render());

        $this->assertSame('Sales <sales@example.com>', $sales['From']);
        $this->assertSame('me@example.com', $explicit['From']);
        $this->assertSame('Example App <app@example.com>', $noAccountFrom['From']);
    }

    public function test_default_account_from_is_used_without_an_account(): void
    {
        config()->set('gmail.default', 'support');
        config()->set('gmail.accounts.support.from', 'support@example.com');

        $this->assertSame('support@example.com', $this->headers((new GMailable)->to('bob@example.com')->plain('Hi')->render())['From']);
    }
}
