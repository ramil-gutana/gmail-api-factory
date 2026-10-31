<?php

namespace App\Shared\Gmail\Interfaces;

use App\Shared\Gmail\GMailable;

interface GmailServiceInterface
{
    public function fetchMessages(array $filters = [], int $maxResults = 10): array;

    public function fetchPage(array $filters = [], int $maxResults = 10, ?string $pageToken = null): array;

    public function getMessage(string $messageId): array;

    public function getAttachment(string $messageId, string $attachmentId): string;

    public function send(GMailable $mailable): string|array;

    public function markAsRead(string $messageId): bool;

    public function markAsUnread(string $messageId): bool;

    public function modifyLabels(string $messageId, array $add = [], array $remove = []): array;

    public function trash(string $messageId): bool;

    public function untrash(string $messageId): bool;
}
