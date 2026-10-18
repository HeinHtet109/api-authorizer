<?php

declare(strict_types=1);

use HeinHtet\ApiAuthorizer\Signing\SignatureSigner;

it('signs the canonical request with HMAC-SHA256 hex output', function () {
    $canonical = 'GET|/api/v1/orders||1771662781|';

    $signature = (new SignatureSigner)->sign($canonical, 'super-secret');

    expect($signature)
        ->toBe(hash_hmac('sha256', $canonical, 'super-secret'))
        ->toHaveLength(64);
});

it('verifies a correct signature', function () {
    $signer = new SignatureSigner;
    $canonical = 'POST|/api/v1/orders||1771662781|{"qty":2}';
    $signature = $signer->sign($canonical, 'super-secret');

    expect($signer->verify($canonical, $signature, 'super-secret'))->toBeTrue();
});

it('rejects a tampered canonical request', function () {
    $signer = new SignatureSigner;
    $signature = $signer->sign('GET|/api/v1/orders||1771662781|', 'super-secret');

    expect($signer->verify('GET|/api/v1/orders||1771662782|', $signature, 'super-secret'))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $signer = new SignatureSigner;
    $canonical = 'GET|/api/v1/orders||1771662781|';
    $signature = $signer->sign($canonical, 'super-secret');

    expect($signer->verify($canonical, $signature, 'other-secret'))->toBeFalse();
});

it('rejects a forged signature', function () {
    $signer = new SignatureSigner;

    expect($signer->verify('GET|/api/v1/orders||1771662781|', str_repeat('a', 64), 'super-secret'))->toBeFalse();
});

it('supports base64 output encoding', function () {
    $canonical = 'GET|/api/v1/orders||1771662781|';
    $signer = new SignatureSigner(encoding: SignatureSigner::ENCODING_BASE64);

    $signature = $signer->sign($canonical, 'super-secret');

    expect(base64_decode($signature, true))
        ->toBe(hash_hmac('sha256', $canonical, 'super-secret', true))
        ->and($signer->verify($canonical, $signature, 'super-secret'))->toBeTrue();
});
