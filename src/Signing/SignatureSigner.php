<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Signing;

final class SignatureSigner
{
    public const ENCODING_HEX = 'hex';

    public const ENCODING_BASE64 = 'base64';

    public function __construct(
        private readonly string $algorithm = 'sha256',
        private readonly string $encoding = self::ENCODING_HEX,
    ) {}

    /**
     * Compute the HMAC of the canonical request with the client secret.
     */
    public function sign(string $canonical, string $secret): string
    {
        $hmac = hash_hmac($this->algorithm, $canonical, $secret, true);

        return $this->encoding === self::ENCODING_BASE64
            ? base64_encode($hmac)
            : bin2hex($hmac);
    }

    /**
     * Timing-safe signature comparison.
     */
    public function verify(string $canonical, string $providedSignature, string $secret): bool
    {
        return hash_equals($this->sign($canonical, $secret), $providedSignature);
    }
}
