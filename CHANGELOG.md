# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Bump the version with `composer bump -- <major|minor|patch|x.y.z>`.

## [Unreleased]

## [1.0.0] - 2026-10-31

### Added

- Initial Gmail API wrapper: `GmailService`, `GMailable`, `GmailManager`,
  `GoogleClientFactory`, the `Gmail` facade and the `gmail-api:access-token` command.
- Composer package definition, PHPUnit/Testbench harness and the `bin/bump-version` script.
