# Changelog

## [Unreleased]

### Added
- New interfaces (intended for testing and internal use) `Tracking\TrackingClientInterface`, `ServiceInfo\ServiceInfoClientInterface` and `Rating\RatingClientInterface`.
- New class `Platform\DevProgram\TrackingClient` implementing `Tracking\TrackingClientInterface` interface.
- New value class `Tracking\PinSummary` holds tracking summary fields.
- The following tracking summary response fields are redacted from the logs: destination postal ID, customer reference fields, signatory name.
- New class `Platform\DevProgram\ServiceInfoClient` implementing `ServiceInfo\ServiceInfoClientInterface` interface. Methods are accessible through `Platform\DevProgram\DevProgram::serviceInfo()`.
- New value class `ServiceInfo\InfoMessage` holds service information messages.

### Changed
- **BREAKING:** `Platform\DevProgram\DevProgram::tracking()` now returns `Tracking\TrackingClientInterface`.
- **BREAKING:** `TrackingClient::getSummary()` only takes a PIN and returns `Tracking\PinSummary`.
- **BREAKING:** `Platform\DevProgram\DevProgram::rating()` now returns `Rating\RatingClientInterface`.
- **BREAKING:** Moved `Rating` to `Platform\DevProgram\RatingClient` implementing `Rating\RatingClientInterface`.
- **BREAKING:** `RatingClient::getRates()` uses separate parameters for option/service codes instead of a combined array. The options array parameter is removed.
- **BREAKING:** `ServiceInfoClient::getServiceInfo()` returns a list of `ServiceInfo\InfoMessage` instead of an array.
- Refactored how the request in `RatingClient::getRates()` is built and removed `openlss/lib-array2xml` dependency.

### Removed
- **BREAKING:** Removed deprecated classes `NCShipment`, `PostOffice`, `Returns`, `Shipment`.
- **BREAKING:** Removed original `Tracking` class.
- **BREAKING:** Removed `getDetails()`, `getSignatureImage()`, `getDeliveryConfirmationCertificate()` methods.
- **BREAKING:** Removed original `ServiceInfo` class.
- **BREAKING:** Removed `ClientBase` and `Exception\ClientException`.

## [0.5.0] - 2026-11-19

### Added
- Optional PSR-3 logger added to `CanadaPost::devProgram()`.
- Successful rating requests/responses are included logged at debug level. Failed requests are logged at error. The following is replaced with \[REDACTED\]:
    - HTTP request headers: authorization, cookie, proxy-authorization.
    - HTTP response headers: set-cookie.
    - XML fields in request/response body with customer PII: postal code, zip code
- New class `Platform\DevProgram\Transport` added to handle Guzzle requests.
- API error codes / messages are included in `Exception\CanadaPostException`.
- Optional timeout parameter added to `CanadaPost::devProgram()` (default 30 seconds).

### Changed
- **BREAKING:** `Rating` constructor changed to use `Platform\DevProgram\Transport` and class no longer extends `ClientBase`.
- **BREAKING:** `Rating` no longer passes options directly to Guzzle options (e.g. handler, timeout), use the new `timeout` parameter on `CanadaPost::devProgram()`.
- **BREAKING:** Guzzle exception on HTTP 4xx/5xx no longer included as `Exception\CanadaPostException::getPrevious()`.
- **BREAKING:** `Rating::parsePriceQuotes()` now takes `\DOMDocument` instead of string.
- Success or error is detected from XML content in the response body. `Exception\CanadaPostException` will be thrown for API errors and invalid / empty / unrecognized XML. 
- Error messages no longer include input values.

### Fixed
- Lowercase service/option codes are converted to uppercase before transmitting request.

## [0.4.0] - 2026-11-12

### Added
- New class `Rating\Destination` added to normalize and validate address data (country code, postal/zip code).
- New class `Rating\Parcel` added to normalize and validate parcel data (weight, length, width, height).
- Optional parcel dimensions are now included in rate requests.
- Exceptions thrown by the library implement `Exception\ExceptionInterface`.
- Invalid input (before the API request is sent) throws `Exception\InvalidArgumentException`.
- Rating and tracking requests should go through the new class and method `CanadaPost::devProgram()`, which creates a client for the Canada Post Developer Program (XML). This defaults to the Canada Post development/sandbox environment.
- New class `Rating\PriceQuote` added to hold shipping service price quote data. Also provides helper methods for total tax and pre-tax amount.
- Invalid responses from the Canada Post API now throw `Exception\CanadaPostException`.

### Changed
- **BREAKING:** Class namespace has been changed from `CanadaPost\` to `iswilson\CanadaPost\`. Consumers of this library should update their `use` statements. Package no longer conflicts with `thejacer87/canadapost-api`.
- **BREAKING:** Params for `Rating::getRates()` changed to accept `Rating\Destination` and `Rating\Parcel`.
- **BREAKING:** Return value of `Rating::getRates()` changed from multidimensional array to a list of `Rating\PriceQuote` objects.
- **BREAKING:** Exceptions for API errors thrown by `Rating::getRates()` are now wrapped with `Exception\CanadaPostException`.
- Origin postal codes are validated and normalized before sending the request. An invalid origin postal code throws `Exception\InvalidArgumentException`.
- Now uses Canada Post `rate-v4` interface (was `rate-v3`), required for international destinations with postal codes.

### Deprecated
- Classes `Shipment`, `NCShipment`, `PostOffice`, `Returns` are unmaintained. No replacement is currently planned. They will be removed before version 1.0.0 is released.

### Removed
- Removed discontinued Priority Worldwide services.

### Fixed
- US and international price quotes are now supported.

## [0.3.1] - 2026-10-30

### Security
- Guzzle debug output is no longer enabled when `env` is set to `dev` (the default). It printed request headers, which included API credentials. If you used `dev`, regenerate your development API keys.

## [0.3.0] - 2026-10-26

### Changed
- Forked package from thejacer87/canadapost-api to iswilson/canadapost-api.
- Now requires PHP 8.3+ and Guzzle 7.

[Unreleased]: https://github.com/iswilson/canadapost-api/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/iswilson/canadapost-api/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/iswilson/canadapost-api/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/iswilson/canadapost-api/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/iswilson/canadapost-api/releases/tag/v0.3.0