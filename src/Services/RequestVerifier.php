<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Services;

use HeinHtet\ApiAuthorizer\Clients\ClientRepositoryInterface;
use HeinHtet\ApiAuthorizer\Exceptions\ApiAuthorizationException;
use HeinHtet\ApiAuthorizer\Signing\CanonicalRequest;
use HeinHtet\ApiAuthorizer\Signing\SignatureSigner;
use Illuminate\Http\Request;

final class RequestVerifier
{
    public function __construct(
        private readonly CanonicalRequest $canonicalRequest,
        private readonly SignatureSigner $signer,
        private readonly ClientRepositoryInterface $clients,
    ) {}

    /**
     * Verify the signed request headers against the canonical request.
     *
     * @throws ApiAuthorizationException
     */
    public function verify(Request $request): VerifiedClient
    {
        $poweredByHeader = (string) config('api-authorizer.headers.powered_by', 'X-POWERED-BY');
        $timestampHeader = (string) config('api-authorizer.headers.timestamp', 'X-TIMESTAMP');
        $signatureHeader = (string) config('api-authorizer.headers.signature', 'X-SIGNATURE');
        $requestIdHeader = (string) config('api-authorizer.headers.request_id', 'X-REQUEST-ID');

        $poweredBy = $request->header($poweredByHeader);
        $timestamp = $request->header($timestampHeader);
        $signature = $request->header($signatureHeader);
        $requestId = $request->header($requestIdHeader);

        $missing = array_keys(array_filter([
            $poweredByHeader => $poweredBy,
            $timestampHeader => $timestamp,
            $signatureHeader => $signature,
        ], static fn (?string $value): bool => $value === null || $value === ''));

        if ($missing !== []) {
            throw ApiAuthorizationException::missingHeaders($missing);
        }

        if (! ctype_digit($timestamp)) {
            throw ApiAuthorizationException::invalidTimestamp();
        }

        $tolerance = (int) config('api-authorizer.tolerance', 300);

        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw ApiAuthorizationException::expiredTimestamp();
        }

        $secret = $this->clients->getSecret($poweredBy);

        if ($secret === null) {
            throw ApiAuthorizationException::unknownClient();
        }

        $canonical = $this->canonicalRequest->build(
            method: $request->method(),
            path: $request->getPathInfo(),
            query: $request->getQueryString() ?? '',
            timestamp: (int) $timestamp,
            body: $request->getContent(),
        );

        if (! $this->signer->verify($canonical, $signature, $secret)) {
            throw ApiAuthorizationException::invalidSignature();
        }

        return new VerifiedClient(
            poweredBy: $poweredBy,
            timestamp: (int) $timestamp,
            requestId: $requestId !== '' ? $requestId : null,
        );
    }
}
