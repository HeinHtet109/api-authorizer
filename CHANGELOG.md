# Changelog

All notable changes to `api-authorizer` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-10-18

### Added

- `VerifyApiSignature` middleware (`api.authorizer` alias) that authenticates requests with the
  `X-POWERED-BY`, `X-TIMESTAMP` and `X-SIGNATURE` headers.
- Canonical request format `METHOD|PATH|SORTED_QUERY|TIMESTAMP|BODY` with query parameters sorted
  alphabetically by key, then value.
- HMAC-SHA256 signing with hex or base64 encoding and timing-safe verification.
- Timestamp tolerance window (default ±300 seconds) to prevent replay attacks.
- Multiple clients, each with its own secret, resolved from config or a custom
  `ClientRepositoryInterface` implementation.
- `ApiSigner` service and `ApiAuthorizer` facade for building signed headers in PHP.
- Optional `X-REQUEST-ID` tracing, echoed back on every response.
- JSON error envelope with configurable `status_code` values per failure.
- Non-production bypass header for local development.
- Verbose logging that never records secrets or signatures.
