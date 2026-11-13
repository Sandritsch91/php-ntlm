# Changelog

## [Unreleased]

### Changed
- Package renamed to `sandritsch91/php-ntlm`; it replaces `jamesiarmes/php-ntlm`.
- PHP 8.5 support: `__doRequest()` accepts the new `$uriParserClass` parameter.

### Fixed
- `__getLastRequest()`, `__getLastResponse()` and `__getLastResponseHeaders()`
  returned null on PHP 8.1+ because the parent's properties became private.
- `__getLastRequestHeaders()` no longer throws before the first request.

## 1.0.0 - 2017-10-03

First stable release.

### Changed
- Fixed broken URL in README.

## 1.0.0-beta.1 - 2016-12-30

### Added
- Composer support.
- Expect header to avoid potential hangs.
- Method to retrieve the status code of the last request.
- Last request/response method support.
- Option to strip bad characters from the request.

### Changed
- Moved out of the php-ews library into it's own project.
