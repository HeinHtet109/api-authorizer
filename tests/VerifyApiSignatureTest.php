<?php

declare(strict_types=1);

use HeinHtet\ApiAuthorizer\Signing\ApiSigner;

it('allows a GET request carrying a valid signature', function () {
    $headers = $this->signedHeaders('GET', '/api/v1/orders', 'page=1&status=paid');

    $this->getJson('/api/v1/orders?page=1&status=paid', $headers)
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('allows a GET request without query parameters', function () {
    $headers = $this->signedHeaders('GET', '/api/v1/orders');

    $this->getJson('/api/v1/orders', $headers)->assertOk();
});

it('accepts query parameters in a different order than they are sent', function () {
    $headers = $this->signedHeaders('GET', '/api/v1/orders', 'status=paid&page=1');

    $this->getJson('/api/v1/orders?page=1&status=paid', $headers)->assertOk();
});

it('allows a POST request signing the raw JSON body', function () {
    $payload = ['item' => 'SKU-1', 'qty' => 2];
    $body = json_encode($payload);
    $headers = $this->signedHeaders('POST', '/api/v1/orders', '', $body);

    $this->postJson('/api/v1/orders', $payload, $headers)->assertOk();
});

it('allows a PUT request signing the raw JSON body', function () {
    $payload = ['status' => 'paid'];
    $body = json_encode($payload);
    $headers = $this->signedHeaders('PUT', '/api/v1/orders', '', $body);

    $this->putJson('/api/v1/orders', $payload, $headers)->assertOk();
});

it('rejects a request with no signed headers', function () {
    $response = $this->getJson('/api/v1/orders');

    $response->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'status_code' => 1004,
            'message' => 'Missing required headers: X-POWERED-BY, X-TIMESTAMP, X-SIGNATURE.',
            'errors' => ['code' => 'missing_header'],
        ]);
});

it('rejects a request missing only the signature header', function () {
    $headers = $this->signedHeaders();
    unset($headers['X-SIGNATURE']);

    $this->getJson('/api/v1/orders', $headers)
        ->assertUnauthorized()
        ->assertJson([
            'status_code' => 1004,
            'message' => 'Missing required headers: X-SIGNATURE.',
        ]);
});

it('rejects a non-numeric timestamp', function () {
    $headers = $this->signedHeaders(timestamp: time(), overrides: ['X-TIMESTAMP' => 'not-a-number']);

    $this->getJson('/api/v1/orders', $headers)
        ->assertUnauthorized()
        ->assertJson([
            'status_code' => 1003,
            'message' => 'Invalid timestamp.',
            'errors' => ['code' => 'invalid_timestamp'],
        ]);
});

it('rejects a timestamp outside the tolerance window in the past', function () {
    $headers = $this->signedHeaders(timestamp: time() - 400);

    $this->getJson('/api/v1/orders', $headers)
        ->assertUnauthorized()
        ->assertJson([
            'status_code' => 1003,
            'message' => 'Request expired.',
            'errors' => ['code' => 'expired_timestamp'],
        ]);
});

it('rejects a timestamp outside the tolerance window in the future', function () {
    $headers = $this->signedHeaders(timestamp: time() + 400);

    $this->getJson('/api/v1/orders', $headers)
        ->assertUnauthorized()
        ->assertJson(['message' => 'Request expired.']);
});

it('accepts a timestamp inside the tolerance window', function () {
    $headers = $this->signedHeaders(timestamp: time() - 290);

    $this->getJson('/api/v1/orders', $headers)->assertOk();
});

it('rejects an unknown powered-by client', function () {
    $headers = $this->signedHeaders(overrides: ['X-POWERED-BY' => 'UNKNOWN-APP']);

    $this->getJson('/api/v1/orders', $headers)
        ->assertUnauthorized()
        ->assertJson([
            'status_code' => 1003,
            'message' => 'Invalid client.',
            'errors' => ['code' => 'unknown_client'],
        ]);
});

it('rejects an invalid signature', function () {
    $headers = $this->signedHeaders();
    $headers['X-SIGNATURE'] = str_repeat('a', 64);

    $this->getJson('/api/v1/orders', $headers)
        ->assertUnauthorized()
        ->assertJson([
            'status_code' => 1003,
            'message' => 'Invalid signature.',
            'errors' => ['code' => 'invalid_signature'],
        ]);
});

it('rejects a request whose body was modified after signing', function () {
    $signedBody = json_encode(['qty' => 2]);
    $headers = $this->signedHeaders('POST', '/api/v1/orders', '', $signedBody);

    $this->postJson('/api/v1/orders', ['qty' => 999], $headers)
        ->assertUnauthorized()
        ->assertJson(['message' => 'Invalid signature.']);
});

it('rejects a request whose query was modified after signing', function () {
    $headers = $this->signedHeaders('GET', '/api/v1/orders', 'page=1');

    $this->getJson('/api/v1/orders?page=2', $headers)
        ->assertUnauthorized()
        ->assertJson(['message' => 'Invalid signature.']);
});

it('exposes the verified client on the request attributes', function () {
    $headers = $this->signedHeaders('GET', '/api/v1/whoami');

    $this->getJson('/api/v1/whoami', $headers)
        ->assertOk()
        ->assertJson(['client' => 'acme']);
});

it('echoes X-REQUEST-ID back on failed requests', function () {
    $this->getJson('/api/v1/orders', ['X-REQUEST-ID' => 'req-123'])
        ->assertUnauthorized()
        ->assertHeader('X-REQUEST-ID', 'req-123');
});

it('passes the request id through on successful requests', function () {
    $headers = $this->signedHeaders('GET', '/api/v1/whoami', requestId: 'req-456');

    $this->getJson('/api/v1/whoami', $headers)
        ->assertOk()
        ->assertJson(['client' => 'acme']);
});

it('allows bypassing verification when enabled', function () {
    config()->set('api-authorizer.bypass', [
        'enabled' => true,
        'header' => 'X-BY-PASS',
        'key' => 'dev-bypass-key',
    ]);

    $this->getJson('/api/v1/orders', ['X-BY-PASS' => 'dev-bypass-key'])->assertOk();
});

it('rejects a wrong bypass key', function () {
    config()->set('api-authorizer.bypass', [
        'enabled' => true,
        'header' => 'X-BY-PASS',
        'key' => 'dev-bypass-key',
    ]);

    $this->getJson('/api/v1/orders', ['X-BY-PASS' => 'wrong-key'])->assertUnauthorized();
});

it('passes everything through when the package is disabled', function () {
    config()->set('api-authorizer.enabled', false);

    $this->getJson('/api/v1/orders')->assertOk();
});

it('uses custom header names when configured', function () {
    config()->set('api-authorizer.headers', [
        'powered_by' => 'X-Client-Id',
        'timestamp' => 'X-Request-Time',
        'signature' => 'X-Auth-Sign',
        'request_id' => 'X-Correlation-Id',
    ]);

    $headers = app(ApiSigner::class)->headers(
        poweredBy: self::POWERED_BY,
        secret: self::SECRET,
        method: 'GET',
        path: '/api/v1/orders',
    );

    expect($headers)->toHaveKeys(['X-Client-Id', 'X-Request-Time', 'X-Auth-Sign'])
        ->not->toHaveKey('X-SIGNATURE');

    $this->getJson('/api/v1/orders', $headers)->assertOk();
});
