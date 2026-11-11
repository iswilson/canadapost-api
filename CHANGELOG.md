# Changelog

## [Unreleased]

### Changed
- **BREAKING:** Class namespace has been changed from `CanadaPost\` to `iswilson\CanadaPost\`. Consumers of this library should update their `use` statements. Package no longer conflicts with `thejacer87/canadapost-api`.
- **BREAKING:** Params for `Rating::getRates()` changed to accept `Rating\Destination` and `Rating\Parcel`.
- **BREAKING:** Return value of `Rating::getRates()` changed from multidimensional array to a list of `Rating\PriceQuote` objects.
- Origin postal codes are validated and normalized before sending the request. An invalid origin postal code throws `Exception\InvalidArgumentException`.
- Now uses Canada Post `rate-v4` interface (was `rate-v3`), required for international destinations with postal codes.

### Added
- New class `Rating\Destination` added to normalize and validate address data (country code, postal/zip code).
- New class `Rating\Parcel` added to normalize and validate parcel data (weight, length, width, height).
- Optional parcel dimensions are now included in rate requests.
- Exceptions thrown by the library implement `Exception\ExceptionInterface`.
- Invalid input (before the API request is sent) throws `Exception\InvalidArgumentException`.
- Rating and tracking requests should go through the new class and method `CanadaPost::devProgram()`, which creates a client for the Canada Post Developer Program (XML). This defaults to the Canada Post development/sandbox environment.
- New class `Rating\PriceQuote` added to hold shipping service price quote data. Also provides helper methods for total tax and pre-tax amount.
- Invalid responses from the Canada Post API now throw `Exception\CanadaPostException`.

### Fixed
- US and international price quotes are now supported.

### Deprecated
- Classes `Shipment`, `NCShipment`, `PostOffice`, `Returns` are unmaintained. No replacement is currently planned. They will be removed before version 1.0.0 is released.

## [0.3.1] - 2026-10-30

### Security
- Guzzle debug output is no longer enabled when `env` is set to `dev` (the default). It printed request headers, which included API credentials. If you used `dev`, regenerate your development API keys.

## [0.3.0] - 2026-10-26

### Changed
- Forked package from thejacer87/canadapost-api to iswilson/canadapost-api.
- Now requires PHP 8.3+ and Guzzle 7.
