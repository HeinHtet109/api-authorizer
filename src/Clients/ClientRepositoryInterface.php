<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Clients;

interface ClientRepositoryInterface
{
    /**
     * Resolve the secret for a client identified by the X-POWERED-BY header value.
     * Return null when the client is unknown.
     */
    public function getSecret(string $poweredBy): ?string;
}
