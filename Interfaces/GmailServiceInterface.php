<?php

namespace App\Shared\Gmail\Interfaces;

use App\Shared\Gmail\GMailable;

interface GmailServiceInterface
{
    public function fetchMessages(array $filters): array;

    public function send(GMailable $mailable): string|array;

    public function markAsRead(string $threadId): bool;
}
