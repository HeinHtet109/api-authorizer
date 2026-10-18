# Laravel API Authorizer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/heinhtet/api-authorizer.svg)](https://packagist.org/packages/heinhtet/api-authorizer)
[![Tests](https://github.com/heinhtet/api-authorizer/actions/workflows/tests.yml/badge.svg)](https://github.com/heinhtet/api-authorizer/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/heinhtet/api-authorizer.svg)](https://packagist.org/packages/heinhtet/api-authorizer)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Protect public and internal Laravel APIs with HMAC-SHA256 request signing. Every request must carry a
signed set of headers (`X-POWERED-BY`, `X-TIMESTAMP`, `X-SIGNATURE`) that prove the caller is authentic,
that the request was not tampered with in transit, and that it is not a replay of a previously captured
request.

This package is the reusable, hardened version of an in-app middleware: it fixes the timestamp check,
sorts query parameters into the documented canonical order, supports multiple clients with independent
secrets, never logs secrets or signatures, and ships with tests and a client-side signer.

- Authenticity — only holders of a client secret can produce a valid signature.
- Integrity — method, path, query string and body are all covered by the HMAC.
- Replay protection — requests outside the configured time window are rejected.
- Multi-tenant — every `X-POWERED-BY` client maps to its own secret (config or your own repository).
- Zero surprises — timing-safe comparison, canonical query sorting, JSON error envelope, request tracing.

## Requirements

- PHP 8.3+
- Laravel 10, 11, 12 or 13

## Installation

```bash
composer require heinhtet/api-authorizer
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=api-authorizer-config
```

Add the credentials to your `.env`:

```dotenv
API_AUTHORIZER_BYPASS_ENABLED=false
API_AUTHORIZER_BYPASS_KEY=

# One entry per client, keyed by the exact X-POWERED-BY value.
API_AUTHORIZER_SECRET_ACME=super-secret-key
```

```php
// config/api-authorizer.php
'clients' => [
    'acme' => env('API_AUTHORIZER_SECRET_ACME'),
],
```

> Secrets are per client. Never commit them, never log them, never ship them inside a browser bundle.

## Usage

The middleware is registered under the `api.authorizer` alias. Apply it to the APIs you want to protect.

```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::middleware('api.authorizer')->prefix('v1')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
});
```

Or globally for every API route in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(append: [
        \HeinHtet\ApiAuthorizer\Middleware\VerifyApiSignature::class,
    ]);
})
```

Or on a single route:

```php
Route::get('/profile', ProfileController::class)
    ->middleware(\HeinHtet\ApiAuthorizer\Middleware\VerifyApiSignature::class);
```

### Accessing the verified client

Once a request passes verification the resolved client is attached to the request attributes:

```php
$client = $request->attributes->get('api_authorizer');

$client->poweredBy; // "acme"
$client->timestamp; // 1771662781
$client->requestId; // "req-123" or null
```

## Required headers

| Header         | Description                                        | Notes                                          |
| -------------- | -------------------------------------------------- | ---------------------------------------------- |
| `X-POWERED-BY` | Unique identifier of the frontend client or app    | Maps to a secret on the server                 |
| `X-TIMESTAMP`  | Current Unix timestamp in seconds (UTC)            | Used for replay attack prevention              |
| `X-SIGNATURE`  | HMAC-SHA256 of the canonical request               | Ensures integrity and authenticity             |
| `X-REQUEST-ID` | Optional correlation id for tracing                | Echoed back in the response, useful for logs   |

The header names are configurable (see [Configuration](#configuration)).

## Canonical request format

The signature is computed over this exact string:

```
METHOD | PATH | SORTED_QUERY | TIMESTAMP | BODY
```

| Component      | Rule                                                                                            |
| -------------- | ----------------------------------------------------------------------------------------------- |
| `METHOD`       | HTTP method, uppercase (`GET`, `POST`, `PUT`, ...)                                              |
| `PATH`         | API path only, e.g. `/api/v1/orders` — no scheme, host or query string                          |
| `SORTED_QUERY` | Query parameters sorted alphabetically by key (then value), joined with `&`. Empty when none.   |
| `TIMESTAMP`    | The same value sent in `X-TIMESTAMP`                                                            |
| `BODY`         | Raw JSON request body for `POST`/`PUT`/`PATCH`/`DELETE`. Always empty for `GET`/`HEAD`.         |

Example GET with query parameters:

```
GET|/api/v1/orders|page=1&status=paid|1771662781|
```

Example POST with a JSON body:

```
POST|/api/v1/orders||1771662781|{"item":"SKU-1","qty":2}
```

Both client and server must build this string identically. Query parameters may be sent in any order —
the package sorts them before signing and before verifying, so `status=paid&page=1` and
`page=1&status=paid` produce the same signature.

## Signature generation

- Algorithm: **HMAC-SHA256**
- Key: the client secret assigned by the backend (`API_AUTHORIZER_SECRET_*`)
- Input: the canonical request string
- Output: lowercase hex (default) or base64 — configure `api-authorizer.encoding`

Worked example, using the secret `super-secret-key`:

```
# GET /api/v1/orders?page=1&status=paid at timestamp 1771662781
canonical = GET|/api/v1/orders|page=1&status=paid|1771662781|
signature = ed04e9dd82afa0e411bc3b88299fd7e4eb42c7a4e10a23df4f4cce509798df6b

# POST /api/v1/orders, raw body {"item":"SKU-1","qty":2}
canonical = POST|/api/v1/orders||1771662781|{"item":"SKU-1","qty":2}
signature = 02323114c2fcce6b9bbda62208cd6c3932211e485c50494bc6b118d4c07b222c
```

> The body is signed byte-for-byte. Send exactly the JSON you signed — the server hashes the raw
> request body, not a re-serialized copy.

### Signing from PHP (server-to-server)

Use the bundled signer, which builds the headers for you:

```php
use HeinHtet\ApiAuthorizer\Facades\ApiAuthorizer;

$headers = ApiAuthorizer::headers(
    poweredBy: 'acme',
    secret: config('api-authorizer.clients.acme'),
    method: 'GET',
    path: '/api/v1/orders',
    query: 'page=1&status=paid',
);

Http::withHeaders($headers)->get(config('services.orders.url').'/api/v1/orders?page=1&status=paid');
```

Or sign an existing request instance:

```php
$headers = ApiAuthorizer::forRequest($request, 'acme', $secret);
```

### Signing without this package (any language)

The algorithm is plain HMAC-SHA256 over the canonical string, so any HTTP client can implement it:

```php
$timestamp = time();
$canonical = implode('|', [
    'GET',
    '/api/v1/orders',
    'page=1&status=paid', // sorted by key
    $timestamp,
    '',                   // empty for GET
]);

$signature = hash_hmac('sha256', $canonical, $secret);
```

```javascript
// Node.js example. Browsers must never hold the secret: proxy through a BFF.
import { createHmac } from "node:crypto";

const timestamp = Math.floor(Date.now() / 1000);
const sortedQuery = "page=1&status=paid"; // sorted by key
const canonical = ["GET", "/api/v1/orders", sortedQuery, timestamp, ""].join("|");
const signature = createHmac("sha256", secret).update(canonical).digest("hex");
```

### curl example

```bash
curl --location 'https://www.sample.com/api/v1/orders?page=1&status=paid' \
  --header 'X-POWERED-BY: acme' \
  --header 'X-TIMESTAMP: 1771662781' \
  --header 'X-SIGNATURE: ed04e9dd82afa0e411bc3b88299fd7e4eb42c7a4e10a23df4f4cce509798df6b'
```

> `X-TIMESTAMP` must be within the allowed window of the server clock. Generate the timestamp and the
> signature in the same step — a hardcoded pair only works while it is fresh.

## Timestamp validation

The client sends a UTC timestamp in seconds. The backend accepts a configurable window
(`tolerance`, default `300` seconds / ±5 minutes) to absorb clock skew and rejects anything outside
of it as `Request expired.` — this is what makes replaying a captured request useless.

## Error responses

Rejected requests return HTTP `401` with a JSON envelope:

```json
{
  "success": false,
  "status_code": 1005,
  "message": "Invalid signature.",
  "errors": {
    "code": "invalid_signature"
  }
}
```

| `errors.code`       | `status_code` (default) | Message                                            |
| ------------------- | ----------------------- | -------------------------------------------------- |
| `missing_header`    | 1001                    | `Missing required headers: X-POWERED-BY, ...`      |
| `invalid_timestamp` | 1002                    | `Invalid timestamp.`                               |
| `expired_timestamp` | 1003                    | `Request expired.`                                 |
| `unknown_client`    | 1004                    | `Invalid client.`                                  |
| `invalid_signature` | 1005                    | `Invalid signature.`                               |

When an `X-REQUEST-ID` header is present it is echoed back on both successful and rejected responses so
you can correlate client and server logs.

## Configuration

```php
// config/api-authorizer.php
return [
    'enabled' => env('API_AUTHORIZER_ENABLED', true),

    'headers' => [
        'powered_by' => 'X-POWERED-BY',
        'timestamp' => 'X-TIMESTAMP',
        'signature' => 'X-SIGNATURE',
        'request_id' => 'X-REQUEST-ID',
    ],

    'tolerance' => env('API_AUTHORIZER_TOLERANCE', 300), // seconds

    'algorithm' => 'sha256',
    'encoding' => 'hex', // hex | base64

    'clients' => [
        'acme' => env('API_AUTHORIZER_SECRET_ACME'),
    ],

    'bypass' => [
        'enabled' => env('API_AUTHORIZER_BYPASS_ENABLED', false),
        'header' => 'X-BY-PASS',
        'key' => env('API_AUTHORIZER_BYPASS_KEY'),
    ],

    'logging' => [
        'enabled' => env('API_AUTHORIZER_LOGGING', false),
        'channel' => env('API_AUTHORIZER_LOG_CHANNEL'),
    ],

    'error_codes' => [
        'missing_header' => 1001,
        'invalid_timestamp' => 1002,
        'expired_timestamp' => 1003,
        'unknown_client' => 1004,
        'invalid_signature' => 1005,
    ],
];
```

The `error_codes` map lets you align `status_code` values with your application's own error catalog.

### Storing clients in the database

Implement `ClientRepositoryInterface` and bind it in a service provider to resolve secrets from the
database, a cache or a secrets manager instead of the config file:

```php
use HeinHtet\ApiAuthorizer\Clients\ClientRepositoryInterface;

final class DatabaseClientRepository implements ClientRepositoryInterface
{
    public function getSecret(string $poweredBy): ?string
    {
        return ApiClient::query()
            ->where('powered_by', $poweredBy)
            ->value('secret');
    }
}
```

```php
$this->app->bind(ClientRepositoryInterface::class, DatabaseClientRepository::class);
```

### Development bypass

For local development you can allow a request to skip verification with a shared bypass key:

```dotenv
API_AUTHORIZER_BYPASS_ENABLED=true
API_AUTHORIZER_BYPASS_KEY=local-dev-key
```

```bash
curl --header 'X-BY-PASS: local-dev-key' https://localhost/api/v1/orders
```

Keep the bypass disabled in production and store the key in `.env` only.

## Security best practices

- Issue a unique secret per client and rotate it if a device or build is compromised.
- Store secrets in secure device storage: iOS Keychain, Android Keystore, OS credential store.
- Never embed secrets in browser-side code. Prefer a backend-for-frontend (BFF) that signs on behalf
  of the browser.
- Always run signed APIs over HTTPS.
- Never log secrets or signatures; this package only logs non-sensitive metadata (and only when
  logging is enabled).
- Keep the timestamp tolerance small enough to limit replay windows but large enough for clock skew.

## Testing

```bash
composer test   # pest
composer pint   # code style
```

To test your own signed routes, let the package sign the request for you:

```php
use HeinHtet\ApiAuthorizer\Facades\ApiAuthorizer;

$headers = ApiAuthorizer::headers(
    poweredBy: 'acme',
    secret: config('api-authorizer.clients.acme'),
    method: 'GET',
    path: '/api/v1/orders',
    query: 'page=1',
);

$this->getJson('/api/v1/orders?page=1', $headers)->assertOk();
```

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request. Security issues are
handled privately as described in [SECURITY.md](SECURITY.md), not in the public issue tracker.
Release notes live in [CHANGELOG.md](CHANGELOG.md).

## License

API Authorizer is open-sourced software licensed under the [MIT license](LICENSE.md).
