<?php

use Google\Service\Gmail;

return [

    /*
    |--------------------------------------------------------------------------
    | Default account
    |--------------------------------------------------------------------------
    |
    | The account used by Gmail::fetchMessages(), Gmail::send(), ... and when
    | GmailServiceInterface is resolved from the container.
    |
    */

    'default' => env('GMAIL_ACCOUNT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    |
    | credentials: OAuth client JSON downloaded from Google Cloud Console.
    | token:       where `php artisan gmail-api:access-token <account>` stores
    |              the access/refresh token.
    | from:        optional default sender for GMailables sent through this
    |              account, e.g. "Support <support@example.com>".
    |
    */

    'accounts' => [

        'default' => [
            'credentials' => env('GMAIL_CREDENTIALS', storage_path('app/gmail/credentials.json')),
            'token' => env('GMAIL_TOKEN', storage_path('app/gmail/token.json')),
            'from' => env('GMAIL_FROM'),
            'scopes' => [
                Gmail::GMAIL_MODIFY,
            ],
        ],

    ],

];
