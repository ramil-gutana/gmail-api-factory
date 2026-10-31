# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Bump the version with `composer bump -- <major|minor|patch|x.y.z>`.

## [Unreleased]

## [1.2.1] - 2026-10-31

### Added

- `Exceptions\GmailException` (extends `RuntimeException`) for token problems and
  `Exceptions\AccountNotConfiguredException` (extends `InvalidArgumentException`), so
  existing `catch` blocks keep working.
- `GMailable::MODE_HTML`, `MODE_PLAIN` and `MODE_VIEW` constants.

### Changed

- Split `GmailService` internals into `Support\SearchQuery` (filter → Gmail query),
  `Support\MessageParser` (API message → array) and `Support\Base64Url`; the service
  now only talks to the API. `markAsRead()` reuses `modifyLabels()`.
- `GoogleClientFactory` exposes `configure()`, `refresh()`, `readToken()` and
  `saveToken()`; the `gmail-api:access-token` command uses them instead of its own
  copy of the client setup and refresh logic, and resolves `Google\Client` from the container.
- `gmail-api:access-token` messages are reworded consistently, e.g.
  `Gmail account [x] is not configured.` and `Token for account [x] refreshed.`
- `GmailService::send()` declares its actual `string` return type.
- `GmailManager` is bound by class name with `gmail.manager` as its alias; the facade
  docblock reflects that `using()` returns `GmailService`.

## [1.2.0] - 2026-10-31

### Added

- `GmailService::fetchPage($filters, $maxResults, $pageToken)` for pagination;
  `fetchMessages()` accepts `$maxResults` (default stays 10).
- `getMessage($id)`, `getAttachment($messageId, $attachmentId)`, `markAsUnread()`,
  `modifyLabels($id, $add, $remove)`, `trash()` and `untrash()`.
- Parsed messages also include `threadId`, `labelIds`, `snippet`, `messageId`
  (the `Message-ID` header, usable with `GMailable::inReplyTo()`) and `date`.
- Publishable `config/gmail.php` (`php artisan vendor:publish --tag=gmail-config`)
  with a `default` account (`GMAIL_ACCOUNT`).
- `GmailManager` caches one service per account, `using()` without an argument
  returns the default account, `forget()` clears the cache, and unknown methods are
  forwarded to the default account (`Gmail::fetchMessages()`).
- `GmailManager::class` resolves to the `gmail.manager` singleton.

### Changed

- Resolving `GmailServiceInterface` from the container now returns the default
  account's authenticated service (it previously built a `GmailService` around an
  unauthenticated Google client).
- `GmailServiceInterface` declares the new methods; custom implementations of the
  interface need to add them.

## [1.1.0] - 2026-10-31

### Added

- `GMailable` attachments: `attach($path, $name, $mime)` and `attachData($data, $name, $mime)`.
- `GMailable::altText()` for a plain-text alternative next to an HTML/view body.
- `GMailable::replyTo()`, `subject()`, `header()` and `inReplyTo($messageId, $threadId)`;
  `GmailService::send()` files replies under the given Gmail thread.
- `GMailable::view()` data argument is now optional.

### Changed

- `GMailable::render()` builds the MIME message with `symfony/mime` (new dependency,
  already shipped with Laravel). Bodies are quoted-printable encoded and a `Date`
  and `Message-ID` header are added.

### Security

- Header values are encoded, so non-ASCII subjects/names render correctly and
  CR/LF in a subject or address can no longer inject extra headers.

## [1.0.1] - 2026-10-31

### Fixed

- `markAsRead()` now reports success based on the `UNREAD` label being removed;
  it used to return the label iterator state, so it returned `false` for messages
  with no labels left. The parameter is renamed to `$messageId` to match what it is.
- `has_attachment`, `has_userlabels` and `has_nouserlabels` filters now produce
  Gmail's `has:<value>` operators instead of unsupported `has_attachment:1` terms.
- Filter values containing spaces or parentheses are quoted (`subject:"Monthly report"`).
- Parsing no longer passes `null` to `strtr()` for multipart parts without body data,
  and tolerates parts without headers.
- `GoogleClientFactory` throws a clear error when the token file is missing or invalid,
  refreshes expired tokens itself instead of shelling out to Artisan, and fails loudly
  when the refresh is rejected.
- `gmail-api:access-token` stops with a failure exit code when the account is not
  configured or Google returns an error, creates the token directory if needed, and
  has a real description.
- `Gmail` facade docblock names the `using()` argument `$account`.

## [1.0.0] - 2026-10-31

### Added

- Initial Gmail API wrapper: `GmailService`, `GMailable`, `GmailManager`,
  `GoogleClientFactory`, the `Gmail` facade and the `gmail-api:access-token` command.
- Composer package definition, PHPUnit/Testbench harness and the `bin/bump-version` script.
