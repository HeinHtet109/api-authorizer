<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Facades;

use HeinHtet\ApiAuthorizer\Signing\ApiSigner;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, string> headers(string $poweredBy, string $secret, string $method, string $path, string $query = '', string $body = '', ?int $timestamp = null, ?string $requestId = null)
 * @method static array<string, string> forRequest(\Illuminate\Http\Request $request, string $poweredBy, string $secret, ?string $requestId = null)
 *
 * @see ApiSigner
 */
class ApiAuthorizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ApiSigner::class;
    }
}
