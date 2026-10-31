<?php

namespace App\Shared\Gmail\Support;

use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;

/**
 * Flattens a Gmail API Message (format=full) into a plain array.
 */
final class MessageParser
{
    public static function parse(Message $message): array
    {
        $payload = $message->getPayload();

        return [
            'id' => $message->getId(),
            'threadId' => $message->getThreadId(),
            'labelIds' => $message->getLabelIds() ?? [],
            'snippet' => $message->getSnippet(),
            'messageId' => self::header($payload, 'Message-ID'),
            'date' => self::header($payload, 'Date'),
            'from' => self::header($payload, 'From'),
            'to' => self::header($payload, 'To'),
            'subject' => self::header($payload, 'Subject'),
            'cc' => self::header($payload, 'Cc'),
            'bcc' => self::header($payload, 'Bcc'),
            'body' => self::body($payload),
        ];
    }

    public static function header(MessagePart $payload, string $name): ?string
    {
        foreach ($payload->getHeaders() ?? [] as $header) {
            if (strcasecmp($header->getName(), $name) === 0) {
                return $header->getValue();
            }
        }

        return null;
    }

    /**
     * @return array{text: string, html: string, attachments: array<int, array{filename: string, mimeType: string, attachmentId: string}>}
     */
    public static function body(MessagePart $payload): array
    {
        $body = ['text' => '', 'html' => '', 'attachments' => []];

        self::collect($payload, $body);

        return $body;
    }

    private static function collect(MessagePart $part, array &$body): void
    {
        $mimeType = $part->getMimeType();
        $partBody = $part->getBody();
        $data = $partBody?->getData() ? Base64Url::decode($partBody->getData()) : '';

        if ($part->getFilename() && $partBody?->getAttachmentId()) {
            $body['attachments'][] = [
                'filename' => $part->getFilename(),
                'mimeType' => $mimeType,
                'attachmentId' => $partBody->getAttachmentId(),
            ];
        }

        if ($data !== '' && $mimeType === 'text/plain') {
            $body['text'] .= self::normalizeText($data);
        }

        if ($data !== '' && $mimeType === 'text/html') {
            $body['html'] .= $data;
        }

        foreach ($part->getParts() ?? [] as $subPart) {
            self::collect($subPart, $body);
        }
    }

    /**
     * Collapse blank-line runs, trim each line and use PHP_EOL line endings.
     */
    private static function normalizeText(string $text): string
    {
        $text = str_replace(['\\r\\n', '\\n'], PHP_EOL, $text);
        $text = preg_replace("/\n{2,}/", "\n\n", $text);
        $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', $text);

        return str_replace(["\r\n", "\r", "\n"], PHP_EOL, trim($text));
    }
}
