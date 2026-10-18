<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled the middleware passes every request through without
    | verifying the signed headers.
    |
    */

    'enabled' => env('API_AUTHORIZER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Request Headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        'powered_by' => 'X-POWERED-BY',
        'timestamp' => 'X-TIMESTAMP',
        'signature' => 'X-SIGNATURE',
        'request_id' => 'X-REQUEST-ID',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timestamp Tolerance (seconds)
    |--------------------------------------------------------------------------
    |
    | Requests whose X-TIMESTAMP differs from server time by more than this
    | window (past or future) are rejected to prevent replay attacks.
    |
    */

    'tolerance' => env('API_AUTHORIZER_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Signature Algorithm & Encoding
    |--------------------------------------------------------------------------
    |
    | encoding: hex (default, lowercase) or base64.
    |
    */

    'algorithm' => 'sha256',

    'encoding' => 'hex',

    /*
    |--------------------------------------------------------------------------
    | Clients (X-POWERED-BY => Secret)
    |--------------------------------------------------------------------------
    |
    | Each client gets a unique secret issued by the backend. The key is the
    | exact value sent in the X-POWERED-BY header. Store secrets in .env,
    | never commit them and never log them.
    |
    */

    'clients' => [
        // 'acme' => env('API_AUTHORIZER_SECRET_ACME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Bypass
    |--------------------------------------------------------------------------
    |
    | When enabled, a request carrying the bypass header with the configured
    | key skips signature verification. Keep disabled in production.
    |
    */

    'bypass' => [
        'enabled' => env('API_AUTHORIZER_BYPASS_ENABLED', false),
        'header' => 'X-BY-PASS',
        'key' => env('API_AUTHORIZER_BYPASS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Verbose logging of verified/rejected requests. Secrets and signatures
    | are never written to logs.
    |
    */

    'logging' => [
        'enabled' => env('API_AUTHORIZER_LOGGING', false),
        'channel' => env('API_AUTHORIZER_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Codes
    |--------------------------------------------------------------------------
    |
    | Numeric status_code returned in the JSON error body for each failure.
    |
    */

    'error_codes' => [
        'missing_header' => 1001,
        'invalid_timestamp' => 1002,
        'expired_timestamp' => 1003,
        'unknown_client' => 1004,
        'invalid_signature' => 1005,
    ],

];
