# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Bump the version with `composer bump -- <major|minor|patch|x.y.z>`.

## [Unreleased]

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
