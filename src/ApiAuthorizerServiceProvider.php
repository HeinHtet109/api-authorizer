<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer;

use HeinHtet\ApiAuthorizer\Clients\ClientRepositoryInterface;
use HeinHtet\ApiAuthorizer\Clients\ConfigClientRepository;
use HeinHtet\ApiAuthorizer\Middleware\VerifyApiSignature;
use HeinHtet\ApiAuthorizer\Services\RequestVerifier;
use HeinHtet\ApiAuthorizer\Signing\ApiSigner;
use HeinHtet\ApiAuthorizer\Signing\CanonicalRequest;
use HeinHtet\ApiAuthorizer\Signing\SignatureSigner;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ApiAuthorizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-authorizer.php', 'api-authorizer');

        $this->app->singleton(CanonicalRequest::class);

        $this->app->singleton(SignatureSigner::class, function ($app): SignatureSigner {
            return new SignatureSigner(
                algorithm: (string) $app['config']->get('api-authorizer.algorithm', 'sha256'),
                encoding: (string) $app['config']->get('api-authorizer.encoding', SignatureSigner::ENCODING_HEX),
            );
        });

        $this->app->singleton(ClientRepositoryInterface::class, function ($app): ClientRepositoryInterface {
            return new ConfigClientRepository(
                (array) $app['config']->get('api-authorizer.clients', [])
            );
        });

        $this->app->singleton(RequestVerifier::class);

        $this->app->singleton(ApiSigner::class, function ($app): ApiSigner {
            return new ApiSigner(
                canonicalRequest: $app->make(CanonicalRequest::class),
                signer: $app->make(SignatureSigner::class),
                headerNames: (array) $app['config']->get('api-authorizer.headers', []),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/api-authorizer.php' => config_path('api-authorizer.php'),
        ], 'api-authorizer-config');

        $this->app->make(Router::class)->aliasMiddleware('api.authorizer', VerifyApiSignature::class);
    }
}
