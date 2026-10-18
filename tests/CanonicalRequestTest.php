<?php

declare(strict_types=1);

use HeinHtet\ApiAuthorizer\Signing\CanonicalRequest;

it('builds the documented canonical format', function () {
    $canonical = (new CanonicalRequest)->build('GET', '/api/v1/orders', '', 1771662781);

    expect($canonical)->toBe('GET|/api/v1/orders||1771662781|');
});

it('uppercases the HTTP method', function () {
    $canonical = (new CanonicalRequest)->build('post', '/api/v1/orders', '', 1771662781, '{"qty":2}');

    expect($canonical)->toBe('POST|/api/v1/orders||1771662781|{"qty":2}');
});

it('includes the raw body for POST and PUT', function () {
    $signer = new CanonicalRequest;

    expect($signer->build('POST', '/api', '', 1, '{"a":1}'))->toEndWith('|{"a":1}')
        ->and($signer->build('PUT', '/api', '', 1, '{"a":2}'))->toEndWith('|{"a":2}');
});

it('always uses an empty body for GET and HEAD', function () {
    $signer = new CanonicalRequest;

    expect($signer->build('GET', '/api', '', 1, '{"a":1}'))->toBe('GET|/api||1|')
        ->and($signer->build('HEAD', '/api', '', 1, '{"a":1}'))->toBe('HEAD|/api||1|');
});

it('sorts query parameters alphabetically regardless of incoming order', function () {
    $signer = new CanonicalRequest;

    expect($signer->build('GET', '/api/v1/orders', 'status=paid&page=1', 1))
        ->toBe($signer->build('GET', '/api/v1/orders', 'page=1&status=paid', 1))
        ->toBe('GET|/api/v1/orders|page=1&status=paid|1|');
});

it('sorts duplicate keys by value', function () {
    $canonical = (new CanonicalRequest)->build('GET', '/api', 'tag=zeta&tag=alpha', 1);

    expect($canonical)->toBe('GET|/api|tag=alpha&tag=zeta|1|');
});

it('preserves raw query encoding while sorting', function () {
    $canonical = (new CanonicalRequest)->build('GET', '/api', 'q=a%20b&page=2', 1);

    expect($canonical)->toBe('GET|/api|page=2&q=a%20b|1|');
});

it('handles a query string without values', function () {
    $canonical = (new CanonicalRequest)->build('GET', '/api', 'flag', 1);

    expect($canonical)->toBe('GET|/api|flag|1|');
});
