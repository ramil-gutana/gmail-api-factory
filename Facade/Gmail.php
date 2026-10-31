<?php

namespace App\Shared\Gmail\Facade;

use App\Shared\Gmail\GMailable;
use App\Shared\Gmail\GmailManager;
use App\Shared\Gmail\GmailService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static GmailService using(?string $account = null)
 * @method static GmailManager forget(?string $account = null)
 * @method static string getDefaultAccount()
 * @method static array fetchMessages(array $filters = [], int $maxResults = 10)
 * @method static array fetchPage(array $filters = [], int $maxResults = 10, ?string $pageToken = null)
 * @method static array getMessage(string $messageId)
 * @method static string getAttachment(string $messageId, string $attachmentId)
 * @method static string send(GMailable $mailable) Sends through the mailable's account, or the default one
 * @method static bool markAsRead(string $messageId)
 * @method static bool markAsUnread(string $messageId)
 * @method static array modifyLabels(string $messageId, array $add = [], array $remove = [])
 * @method static bool trash(string $messageId)
 * @method static bool untrash(string $messageId)
 *
 * @see GmailManager
 */
class Gmail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gmail.manager';
    }
}
