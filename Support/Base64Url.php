<?php

namespace App\Shared\Gmail\Support;

/**
 * URL-safe base64 as used by the Gmail API for message bodies, attachments and raw messages.
 */
final class Base64Url
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
