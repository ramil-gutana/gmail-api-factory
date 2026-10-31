<?php

namespace App\Shared\Gmail;

use Illuminate\Support\Facades\View;
use Illuminate\View\View as LaravelView;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class GMailable
{
    public const MODE_HTML = 'html';

    public const MODE_PLAIN = 'plain';

    public const MODE_VIEW = 'view';

    public array $to = [];

    public array $cc = [];

    public array $bcc = [];

    public array $replyTo = [];

    public string|LaravelView $body = '';

    public string $mode = '';

    /**
     * Plain-text alternative sent alongside an HTML or view body.
     */
    public ?string $altText = null;

    /**
     * @var array<int, array{path?: string, data?: string, name: ?string, mime: ?string}>
     */
    public array $attachments = [];

    /**
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * Gmail thread the message belongs to (set when replying).
     */
    public ?string $threadId = null;

    public function __construct(
        public string $from,
        public string $subject = ''
    ) {}

    public function to(string|array|null $to): static
    {
        $this->to = $this->normalize($to);

        return $this;
    }

    public function cc(string|array|null $cc): static
    {
        $this->cc = $this->normalize($cc);

        return $this;
    }

    public function bcc(string|array|null $bcc): static
    {
        $this->bcc = $this->normalize($bcc);

        return $this;
    }

    public function replyTo(string|array|null $replyTo): static
    {
        $this->replyTo = $this->normalize($replyTo);

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        $this->body = View::make($view, $data);
        $this->mode = self::MODE_VIEW;

        return $this;
    }

    public function html(string $html): static
    {
        $this->body = $html;
        $this->mode = self::MODE_HTML;

        return $this;
    }

    public function plain(string $plain): static
    {
        $this->body = $plain;
        $this->mode = self::MODE_PLAIN;

        return $this;
    }

    /**
     * Add a plain-text alternative to an HTML or view body (multipart/alternative).
     */
    public function altText(string $text): static
    {
        $this->altText = $text;

        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $mime = null): static
    {
        $this->attachments[] = ['path' => $path, 'name' => $name, 'mime' => $mime];

        return $this;
    }

    public function attachData(string $data, string $name, ?string $mime = null): static
    {
        $this->attachments[] = ['data' => $data, 'name' => $name, 'mime' => $mime];

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Send the message as a reply: sets In-Reply-To/References to the original
     * Message-ID header and, when given, the Gmail thread to file it under.
     */
    public function inReplyTo(string $messageId, ?string $threadId = null): static
    {
        $this->headers['In-Reply-To'] = $messageId;
        $this->headers['References'] = $messageId;
        $this->threadId = $threadId;

        return $this;
    }

    /**
     * @throws Throwable
     */
    private function renderBody(): string
    {
        if ($this->body instanceof LaravelView) {
            return $this->body->render();
        }

        return (string) $this->body;
    }

    /**
     * Build the RFC 2822 message Gmail expects in Message::$raw.
     *
     * @throws Throwable
     */
    public function render(): string
    {
        $email = (new Email)
            ->from(Address::create($this->from))
            ->subject($this->subject);

        $this->addresses($email, 'to', $this->to);
        $this->addresses($email, 'cc', $this->cc);
        $this->addresses($email, 'bcc', $this->bcc);
        $this->addresses($email, 'replyTo', $this->replyTo);

        $body = $this->renderBody();

        if ($this->mode === self::MODE_PLAIN) {
            $email->text($body);
        } else {
            $email->html($body);

            if ($this->altText !== null) {
                $email->text($this->altText);
            }
        }

        foreach ($this->attachments as $attachment) {
            isset($attachment['path'])
                ? $email->attachFromPath($attachment['path'], $attachment['name'], $attachment['mime'])
                : $email->attach($attachment['data'], $attachment['name'], $attachment['mime']);
        }

        foreach ($this->headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        // Symfony drops Bcc from the rendered message, but Gmail reads the
        // Bcc recipients from the raw message (and strips the header itself).
        $bcc = $email->getHeaders()->get('Bcc');

        return ($bcc ? $bcc->toString()."\r\n" : '').$email->toString();
    }

    private function addresses(Email $email, string $method, array $addresses): void
    {
        if ($addresses) {
            $email->{$method}(...array_map(fn (string $address) => Address::create($address), $addresses));
        }
    }

    private function normalize(string|array|null $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(is_array($value) ? $value : [$value]));
    }
}
