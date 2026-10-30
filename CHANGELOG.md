# Changelog

## [0.3.1] - 2026-10-30

### Security
- Guzzle debug output is no longer enabled when `env` is set to `dev` (the default). It printed request headers, which included API credentials. If you used `dev`, regenerate your development API keys.

## [0.3.0] - 2026-10-26

### Changed
- Forked package from thejacer87/canadapost-api to iswilson/canadapost-api.
- Now requires PHP 8.3+ and Guzzle 7.
