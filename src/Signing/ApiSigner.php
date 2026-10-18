<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Signing;

use Illuminate\Http\Request;

/**
 * Client-side helper that builds the signed request headers
 * (X-POWERED-BY, X-TIMESTAMP, X-SIGNATURE, optional X-REQUEST-ID).
 */
final class ApiSigner
{
    /**
     * @param  array<string, string>  $headerNames  Overrides for the default header names,
     *                                              keyed by powered_by|timestamp|signature|request_id.
     */
    public function __construct(
        private readonly CanonicalRequest $canonicalRequest,
        private readonly SignatureSigner $signer,
        private readonly array $headerNames = [],
    ) {}

    /**
     * Build signed headers for an outgoing request.
     *
     * @return array<string, string>
     */
    public function headers(
        string $poweredBy,
        string $secret,
        string $method,
        string $path,
        string $query = '',
        string $body = '',
        ?int $timestamp = null,
        ?string $requestId = null,
    ): array {
        $timestamp ??= time();

        $canonical = $this->canonicalRequest->build($method, $path, $query, $timestamp, $body);

        $headers = [
            $this->headerName('powered_by') => $poweredBy,
            $this->headerName('timestamp') => (string) $timestamp,
            $this->headerName('signature') => $this->signer->sign($canonical, $secret),
        ];

        if ($requestId !== null) {
            $headers[$this->headerName('request_id')] = $requestId;
        }

        return $headers;
    }

    /**
     * Build signed headers for an existing request instance (server-to-server calls, tests).
     *
     * @return array<string, string>
     */
    public function forRequest(Request $request, string $poweredBy, string $secret, ?string $requestId = null): array
    {
        return $this->headers(
            poweredBy: $poweredBy,
            secret: $secret,
            method: $request->getMethod(),
            path: $request->getPathInfo(),
            query: $request->getQueryString() ?? '',
            body: $request->getContent(),
            requestId: $requestId,
        );
    }

    private function headerName(string $key): string
    {
        return $this->headerNames[$key] ?? match ($key) {
            'powered_by' => 'X-POWERED-BY',
            'timestamp' => 'X-TIMESTAMP',
            'signature' => 'X-SIGNATURE',
            'request_id' => 'X-REQUEST-ID',
            default => $key,
        };
    }
}
