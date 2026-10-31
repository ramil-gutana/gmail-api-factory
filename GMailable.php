<?php

namespace App\Shared\Gmail;

use Illuminate\Support\Facades\View;
use Illuminate\View\View as LaravelView;
use Throwable;

class GMailable
{
    public array $to = [];

    public array $cc = [];

    public array $bcc = [];

    public string|LaravelView $body = '';

    public string $mode = '';

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

    public function view(string $view, array $data): static
    {
        $this->body = View::make($view, $data);
        $this->mode = 'view';

        return $this;
    }

    public function html(string $html): static
    {
        $this->body = $html;
        $this->mode = 'html';

        return $this;
    }

    public function plain(string $plain): static
    {
        $this->body = $plain;
        $this->mode = 'plain';

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
     * @throws Throwable
     */
    public function render(): string
    {
        $to = implode(', ', $this->to);
        $cc = implode(', ', $this->cc);
        $bcc = implode(', ', $this->bcc);

        $raw = "From: {$this->from}\r\n";
        $raw .= "To: {$to}\r\n";
        if (! empty($cc)) {
            $raw .= "Cc: {$cc}\r\n";
        }
        if (! empty($bcc)) {
            $raw .= "Bcc: {$bcc}\r\n";
        }
        $raw .= "Subject: {$this->subject}\r\n";
        $raw .= "MIME-Version: 1.0\r\n";

        $body = $this->renderBody();

        if ($this->mode === 'plain') {
            $raw .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $raw .= $body;
        } else {
            $raw .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $raw .= $body;
        }

        return $raw;
    }

    private function normalize(string|array|null $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
