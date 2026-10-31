<?php

namespace Tests\Fixtures;

use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartBody;
use Google\Service\Gmail\MessagePartHeader;

class MessageFactory
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function part(string $mimeType, ?string $data = null, array $parts = [], ?string $filename = null, ?string $attachmentId = null, array $headers = []): MessagePart
    {
        $body = new MessagePartBody;
        if ($data !== null) {
            $body->setData(self::encode($data));
        }
        if ($attachmentId !== null) {
            $body->setAttachmentId($attachmentId);
        }

        $part = new MessagePart;
        $part->setMimeType($mimeType);
        $part->setBody($body);
        $part->setFilename($filename ?? '');
        $part->setParts($parts);
        $part->setHeaders(array_map(function ($name, $value) {
            $header = new MessagePartHeader;
            $header->setName($name);
            $header->setValue($value);

            return $header;
        }, array_keys($headers), $headers));

        return $part;
    }

    public static function message(string $id, MessagePart $payload, array $labelIds = [], ?string $threadId = null): Message
    {
        $message = new Message;
        $message->setId($id);
        $message->setThreadId($threadId ?? $id);
        $message->setLabelIds($labelIds);
        $message->setPayload($payload);

        return $message;
    }
}
