# API Authorizer for Laravel

This application installs `heinhtet/api-authorizer`. The package verifies HMAC-SHA256 signatures on
incoming requests, so only callers that hold a client secret can reach the protected routes. It also
rejects replays. Everything below is behaviour of the installed package, not generic security advice,
and none of it applies to routes that have not opted in.

## Protecting a route

Add the middleware to the routes the client should sign:

```php
Route::middleware('api.authorizer')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});
```

Or append it to the `api` group in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: ['api.authorizer']);
})
```

A request without a valid signature never reaches the controller: the middleware returns HTTP 401 with
a JSON error envelope before the route runs.

## Configuring clients

Publish the config once:

```bash
php artisan vendor:publish --tag=api-authorizer-config
```

Each `X-POWERED-BY` value maps to a secret under `api-authorizer.clients`. Keep the secrets in `.env`,
never in code:

```dotenv
API_AUTHORIZER_SECRET_ACME=super-secret-key
```

To resolve clients from a database or another store, bind your own
`HeinHtet\ApiAuthorizer\Clients\ClientRepositoryInterface` implementation in a service provider. Do not
add caching of secrets without an expiry, and never return the secret to the client.

## What the client signs

The client builds the canonical string and signs it with HMAC-SHA256:

```
METHOD|PATH|SORTED_QUERY|TIMESTAMP|BODY
```

- `METHOD` is upper case (`GET`, `POST`, …).
- `PATH` is the request path with no host and no trailing `?`.
- `SORTED_QUERY` is the query string sorted by key, then value, with the original encoding preserved.
  Empty for a request with no query.
- `TIMESTAMP` is the Unix time in seconds, and it must be the value sent in `X-TIMESTAMP`.
- `BODY` is the raw request body, and it is empty for `GET` and `HEAD`.

The signature goes in `X-SIGNATURE`, encoded as hex (default) or base64 per config. A request whose
`X-TIMESTAMP` is outside `api-authorizer.tolerance` (±300 seconds by default) is treated as an expired
or invalid signature and rejected as a replay.

In PHP, do not build these by hand — use the signer:

```php
use HeinHtet\ApiAuthorizer\Facades\ApiAuthorizer;

$headers = ApiAuthorizer::headers(
    poweredBy: 'acme',
    secret: config('api-authorizer.clients.acme'),
    method: 'POST',
    path: '/api/v1/orders',
    query: '',
    body: json_encode($payload),
);
```

Client SDKs in other languages must follow the canonical rules exactly. A whitespace or ordering
difference changes the signature and the request is rejected.

## Failure responses

Every failure is HTTP 401 with a JSON body:

```json
{
    "success": false,
    "status_code": 1003,
    "message": "Invalid signature.",
    "errors": { "code": "invalid_signature" }
}
```

`missing_header` (`1004`) means a required header is absent; `invalid_timestamp`, `expired_timestamp`,
`unknown_client` and `invalid_signature` (`1003`) cover the rest. The per-code values are configurable
under `api-authorizer.error_codes`.

## Trusting the verified client

Once verification succeeds, the middleware attaches the result to the request, so controllers can read
who called without re-parsing headers:

```php
$client = $request->attributes->get('api_authorizer'); // poweredBy, timestamp, requestId
```

`X-REQUEST-ID` is optional, and when the client sends it the middleware echoes it back on the
response, for both success and failure.

## Local development

The bypass header is disabled by default. Enable it only in local and testing environments, never in
production:

```dotenv
API_AUTHORIZER_BYPASS_ENABLED=true
API_AUTHORIZER_BYPASS_KEY=local-dev-only
```

Do not leave the bypass enabled in a deployed environment: it lets any caller skip signature
verification.

## Testing signed routes

Sign the request with the package instead of hard-coding headers:

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
