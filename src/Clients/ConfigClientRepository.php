<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Clients;

final class ConfigClientRepository implements ClientRepositoryInterface
{
    /**
     * @param  array<string, string>  $clients  Map of powered-by identifier => secret.
     */
    public function __construct(private readonly array $clients) {}

    public function getSecret(string $poweredBy): ?string
    {
        $secret = $this->clients[$poweredBy] ?? null;

        return $secret === null || $secret === '' ? null : $secret;
    }
}
