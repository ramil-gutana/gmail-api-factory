# Gmail API Factory

A multi-account Gmail API wrapper for Laravel: fetch, send, label and trash
messages through the Gmail REST API with OAuth tokens stored per account.

Requires PHP 8.3+ (tested on 8.3 and 8.5) and Laravel 13.

## Installation

The code lives in the `App\Shared\Gmail` namespace, so it can be dropped into
`app/Shared/Gmail` of a Laravel app or installed as a Composer package (the
service provider and `Gmail` facade are auto-discovered).

Publish the config and point it at your Google OAuth client:

```bash
php artisan vendor:publish --tag=gmail-config
```

```php
// config/gmail.php
'default' => env('GMAIL_ACCOUNT', 'support'),

'accounts' => [
    'support' => [
        'credentials' => storage_path('app/gmail/credentials.json'),
        'token' => storage_path('app/gmail/support-token.json'),
        'scopes' => [Google\Service\Gmail::GMAIL_MODIFY],
    ],
],
```

Authorise each account once; expired tokens are refreshed automatically after that:

```bash
php artisan gmail-api:access-token support
```

## Reading mail

```php
use App\Shared\Gmail\Facade\Gmail;

// Default account
$messages = Gmail::fetchMessages(['is' => 'unread', 'has_attachment' => true], maxResults: 25);

// A specific account, page by page
$page = Gmail::using('sales')->fetchPage(['from' => ['a@example.com', 'b@example.com']]);
$next = Gmail::using('sales')->fetchPage([...], 10, $page['nextPageToken']);
```

Each message is an array with `id`, `threadId`, `labelIds`, `snippet`,
`messageId`, `date`, `from`, `to`, `cc`, `bcc`, `subject` and
`body` (`text`, `html`, `attachments`).

Supported filters: `from`, `to`, `cc`, `bcc`, `subject`, `label`, `filename`,
`is`, `in`, `before`, `after`, `older`, `newer`, `older_than`, `newer_than`, and
the flags `has_attachment`, `has_userlabels`, `has_nouserlabels`. Array values
are OR-ed; values with spaces are quoted.

```php
$pdf = Gmail::getAttachment($message['id'], $message['body']['attachments'][0]['attachmentId']);

Gmail::markAsRead($id);
Gmail::markAsUnread($id);
Gmail::modifyLabels($id, add: ['Label_1'], remove: ['INBOX']);
Gmail::trash($id);
Gmail::untrash($id);
```

## Sending mail

```php
use App\Shared\Gmail\GMailable;

$mail = (new GMailable('Support <support@example.com>', 'Your invoice'))
    ->to('customer@example.com')
    ->cc(['billing@example.com'])
    ->bcc('audit@example.com')
    ->replyTo('help@example.com')
    ->view('emails.invoice', ['invoice' => $invoice])   // or ->html() / ->plain()
    ->altText('Your invoice is attached.')
    ->attach(storage_path('invoices/123.pdf'))
    ->attachData($csv, 'items.csv', 'text/csv');

$id = Gmail::using('support')->send($mail);
```

### Mailable classes

Generate a reusable mailable class in `app/Mail/Gmail`:

```bash
php artisan gmail-api:make-mailable OrderShipped
php artisan gmail-api:make-mailable Orders/OrderShipped --view            # + resources/views/mail/gmail/orders/order-shipped.blade.php
php artisan gmail-api:make-mailable OrderShipped --view=emails.shipped    # custom view name
```

Configure the message in `build()`, which runs once before the message is rendered.
When no sender is given, `mail.from` from your app config is used:

```php
class OrderShipped extends GMailable
{
    public function __construct(public Order $order)
    {
        parent::__construct(subject: "Order #{$order->id} shipped");
    }

    protected function build(): void
    {
        $this->to($this->order->customer_email)
            ->view('mail.gmail.order-shipped', ['order' => $this->order]);
    }
}

Gmail::send(new OrderShipped($order));
```

Customise the generated code with `php artisan vendor:publish --tag=gmail-stubs`.

Reply on an existing thread:

```php
$reply = (new GMailable('support@example.com', 'Re: '.$message['subject']))
    ->to($message['from'])
    ->inReplyTo($message['messageId'], $message['threadId'])
    ->plain('Thanks, we are on it.');

Gmail::send($reply);
```

Note: `send()` returns `"Error: ..."` instead of throwing when the Gmail API rejects the message.

## Errors

- `AccountNotConfiguredException` (an `InvalidArgumentException`): the account is missing from `config/gmail.php`.
- `GmailException` (a `RuntimeException`): the token file is missing or invalid, or the token cannot be refreshed.
- `Google\Service\Exception`: the Gmail API rejected a request.

## Development

```bash
composer install
composer test
```

### Versioning

The version lives in `VERSION` and follows [Semantic Versioning](https://semver.org).
Add entries under `## [Unreleased]` in `CHANGELOG.md`, then:

```bash
composer bump -- patch   # or minor, major, or an explicit x.y.z (add --dry-run to preview)
```

This updates `VERSION` and moves the unreleased changelog entries into a dated release section.
