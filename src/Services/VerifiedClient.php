<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Services;

final readonly class VerifiedClient
{
    public function __construct(
        public string $poweredBy,
        public int $timestamp,
        public ?string $requestId = null,
    ) {}
}
