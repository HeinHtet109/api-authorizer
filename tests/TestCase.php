<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Tests;

use HeinHtet\ApiAuthorizer\ApiAuthorizerServiceProvider;
use HeinHtet\ApiAuthorizer\Middleware\VerifyApiSignature;
use HeinHtet\ApiAuthorizer\Signing\ApiSigner;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const POWERED_BY = 'acme';

    protected const SECRET = 'super-secret-key';

    /**
     * @return array<int, string>
     */
    protected function getPackageProviders($app): array
    {
        return [ApiAuthorizerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('api-authorizer.clients', [
            self::POWERED_BY => self::SECRET,
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/api/v1/orders', fn () => response()->json(['ok' => true]))->middleware(VerifyApiSignature::class);
        $router->post('/api/v1/orders', fn () => response()->json(['ok' => true]))->middleware(VerifyApiSignature::class);
        $router->put('/api/v1/orders', fn () => response()->json(['ok' => true]))->middleware(VerifyApiSignature::class);

        $router->get('/api/v1/whoami', fn () => response()->json([
            'client' => request()->attributes->get('api_authorizer')?->poweredBy,
        ]))->middleware(VerifyApiSignature::class);
    }

    /**
     * Build signed headers through the package's own ApiSigner.
     *
     * @return array<string, string>
     */
    protected function signedHeaders(
        string $method = 'GET',
        string $path = '/api/v1/orders',
        string $query = '',
        string $body = '',
        ?int $timestamp = null,
        ?string $requestId = null,
        array $overrides = [],
    ): array {
        return array_merge(
            app(ApiSigner::class)->headers(
                poweredBy: self::POWERED_BY,
                secret: self::SECRET,
                method: $method,
                path: $path,
                query: $query,
                body: $body,
                timestamp: $timestamp,
                requestId: $requestId,
            ),
            $overrides,
        );
    }
}
