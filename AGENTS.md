# Instructions for AI coding agents

This file is for an AI coding agent contributing to this repository. It covers what the package is,
the standards a change must meet, and the traps in this repository.

[`CONTRIBUTING.md`](CONTRIBUTING.md) remains the authority on what the project expects of any change.
This file adds what an agent needs on top of it.

## What this package is

`heinhtet/api-authorizer` is a Laravel **package**, not an application. Other people's applications
install it, so their code depends on how it behaves.

Its contract with those applications is:

- the **canonical string** `METHOD|PATH|SORTED_QUERY|TIMESTAMP|BODY` — the wire format shared with
  every client SDK, in any language;
- the **header names** (`X-POWERED-BY`, `X-TIMESTAMP`, `X-SIGNATURE`, optional `X-REQUEST-ID`);
- the **error envelope** `{success, status_code, message, errors: {code}}` and its HTTP status.

A change to any of these is a breaking change for every signing client, so it belongs to a major
release and it must update `README.md`, `CHANGELOG.md` and the tests together.

## Setup and commands

There is no `artisan` file, so `php artisan` fails here. Orchestra Testbench ships a console:

```bash
composer update        # installs dev tooling (there is no committed composer.lock)
composer test          # vendor/bin/pest
composer pint          # vendor/bin/pint
vendor/bin/pint --test # check style without rewriting files
```

`php artisan make:*` generators tell you the file does not exist. Create each file by hand, next to its
siblings, and copy their structure so it stays in the package namespace and layout.

The suite uses Pest 4 and `phpunit.xml.dist`. In `tests/Pest.php`, `uses(TestCase::class)` must be
followed by `->in(__DIR__)`; a bare call throws at bootstrap on Pest 4.

## Development standards

- **Prove each new test red, then green.** Write the test, watch it fail for the right reason, then
  make it pass. A test written after a fix often passes without the fix.
- **Cover behaviour, not implementation.** Test the canonical string, the verification outcomes and
  the error envelope, not private methods.
- **Never log or commit secrets or signatures.** This is a signing package; a leaked secret in a
  fixture, a test or a log line is a real vulnerability.
- **Keep signatures timing-safe.** Compare with `hash_equals`; never `===`.
- **Write code that runs on every supported major** (PHP 8.3+, Laravel 10–13). An API that exists in
  only one major breaks consumers on the others. CI does not run the suite (Actions are unavailable),
  so run `composer test` locally against more than one Laravel major when you touch version-sensitive code.
- **Answer Laravel questions with Boost's `search-docs` tool**, not from memory. Memory stops at the
  training date and can describe APIs that have since changed.
- **Update `README.md` and `CHANGELOG.md`** for any behaviour, header, config or error-code change.

## Style

Follow `.editorconfig`: four-space indent, LF, final newline. Pint (Laravel preset) enforces
formatting, but match the style of the code around your change rather than reformatting unrelated
lines. Type every parameter, return and property, and keep `declare(strict_types=1);` at the top of
each file.

## Pull requests

Complete `.github/PULL_REQUEST_TEMPLATE.md`. A change produced with an agent says so, and a named
person attests that they read the whole diff and ran the suite.
