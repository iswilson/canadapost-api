# Changelog

## [Unreleased]

### Changed
- **BREAKING:** Class namespace has been changed from `CanadaPost\` to `iswilson\CanadaPost\`. Consumers of this library should update their `use` statements. Package no longer conflicts with `thejacer87/canadapost-api`.
- **BREAKING:** Params for `Rating::getRates()` changed to accept `Rating\Destination` instead of a postal code string.
- Origin postal codes are validated and normalized before sending the request. An invalid origin postal code throws `Exception\InvalidArgumentException`.
- Now uses Canada Post `rate-v4` interface (was `rate-v3`), required for international destinations with postal codes.

### Added
- New class `Rating\Destination` added to normalize and validate address data (country code, postal/zip code).
- Exceptions thrown by the library implement `Exception\ExceptionInterface`.
- Invalid input (before the API request is sent) throws `Exception\InvalidArgumentException`.

### Fixed
- US and international price quotes are now supported.

## [0.3.1] - 2026-10-30

### Security
- Guzzle debug output is no longer enabled when `env` is set to `dev` (the default). It printed request headers, which included API credentials. If you used `dev`, regenerate your development API keys.

## [0.3.0] - 2026-10-26

### Changed
- Forked package from thejacer87/canadapost-api to iswilson/canadapost-api.
- Now requires PHP 8.3+ and Guzzle 7.
