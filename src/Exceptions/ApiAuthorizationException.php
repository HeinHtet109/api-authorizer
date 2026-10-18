<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Exceptions;

use RuntimeException;

final class ApiAuthorizationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missingHeader(string $header): self
    {
        return new self('missing_header', "Missing required header: {$header}.");
    }

    public static function missingHeaders(array $headers): self
    {
        return new self('missing_header', 'Missing required headers: '.implode(', ', $headers).'.');
    }

    public static function invalidTimestamp(): self
    {
        return new self('invalid_timestamp', 'Invalid timestamp.');
    }

    public static function expiredTimestamp(): self
    {
        return new self('expired_timestamp', 'Request expired.');
    }

    public static function unknownClient(): self
    {
        return new self('unknown_client', 'Invalid client.');
    }

    public static function invalidSignature(): self
    {
        return new self('invalid_signature', 'Invalid signature.');
    }
}
