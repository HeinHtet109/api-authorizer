# Security Policy

## Supported versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a vulnerability

Please report security issues privately, not in a public issue.

1. Preferred: open a private report through the repository's **Security** tab
   ("Report a vulnerability"). This keeps the discussion private until a fix is released.
2. Or email the maintainer: `<!-- TODO: replace with your maintainer email -->`.

Include the affected version, a description of the issue, and a proof of concept if you have one.
All reports are acknowledged as soon as possible.

## Scope

This package implements request signing, so the following are in scope:

- signature verification bypass;
- canonical-string ambiguity that lets a different request reuse a signature;
- replay of a request outside the configured tolerance window;
- timing side channels in signature comparison;
- secret or signature disclosure through logs or error responses.

When reporting, redact real client secrets and production signatures. A canonical string is safe to
share; the secret that signs it is not.
