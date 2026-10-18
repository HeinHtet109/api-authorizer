<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Signing;

final class CanonicalRequest
{
    public const SEPARATOR = '|';

    /**
     * Build the canonical string used for HMAC signing:
     *
     *     METHOD|PATH|SORTED_QUERY|TIMESTAMP|BODY
     *
     * @param  string  $method  HTTP method, uppercased by this method (GET, POST, PUT, ...)
     * @param  string  $path  API path only, e.g. /api/v1/orders
     * @param  string  $query  Raw query string, e.g. page=1&status=paid (empty string when none)
     * @param  string|int  $timestamp  Unix timestamp in seconds, same value as the X-TIMESTAMP header
     * @param  string  $body  Raw JSON body. Ignored for GET/HEAD requests (always empty there).
     */
    public function build(string $method, string $path, string $query, string|int $timestamp, string $body = ''): string
    {
        $method = strtoupper($method);
        $body = in_array($method, ['GET', 'HEAD'], true) ? '' : $body;

        return implode(self::SEPARATOR, [
            $method,
            $path,
            $this->sortQuery($query),
            (string) $timestamp,
            $body,
        ]);
    }

    /**
     * Sort raw query segments alphabetically (by key, then value), preserving original encoding.
     *
     * page=1&status=paid  ->  page=1&status=paid
     * status=paid&page=1  ->  page=1&status=paid
     */
    public function sortQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $segments = array_values(array_filter(
            explode('&', $query),
            static fn (string $segment): bool => $segment !== ''
        ));

        usort($segments, function (string $a, string $b): int {
            [$aKey, $aValue] = $this->splitSegment($a);
            [$bKey, $bValue] = $this->splitSegment($b);

            return [$aKey, $aValue] <=> [$bKey, $bValue];
        });

        return implode('&', $segments);
    }

    /**
     * @return array{string, string}
     */
    private function splitSegment(string $segment): array
    {
        $position = strpos($segment, '=');

        if ($position === false) {
            return [$segment, ''];
        }

        return [substr($segment, 0, $position), substr($segment, $position + 1)];
    }
}
