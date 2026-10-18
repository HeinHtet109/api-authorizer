<?php

declare(strict_types=1);

namespace HeinHtet\ApiAuthorizer\Middleware;

use Closure;
use HeinHtet\ApiAuthorizer\Exceptions\ApiAuthorizationException;
use HeinHtet\ApiAuthorizer\Services\RequestVerifier;
use HeinHtet\ApiAuthorizer\Services\VerifiedClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class VerifyApiSignature
{
    public function __construct(private readonly RequestVerifier $verifier) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api-authorizer.enabled', true)) {
            return $next($request);
        }

        try {
            if ($this->bypassed($request)) {
                return $next($request);
            }

            $client = $this->verifier->verify($request);
        } catch (ApiAuthorizationException $exception) {
            $this->logFailure($request, $exception);

            return $this->unauthorized($request, $exception);
        }

        $request->attributes->set('api_authorizer', $client);

        $this->logVerified($request, $client);

        $response = $next($request);

        if ($client->requestId !== null) {
            $response->headers->set(
                (string) config('api-authorizer.headers.request_id', 'X-REQUEST-ID'),
                $client->requestId,
            );
        }

        return $response;
    }

    private function bypassed(Request $request): bool
    {
        if (! config('api-authorizer.bypass.enabled', false)) {
            return false;
        }

        $key = config('api-authorizer.bypass.key');

        if (! is_string($key) || $key === '') {
            return false;
        }

        $provided = $request->header(config('api-authorizer.bypass.header', 'X-BY-PASS'));

        return $provided !== null && hash_equals($key, $provided);
    }

    private function unauthorized(Request $request, ApiAuthorizationException $exception): Response
    {
        $headers = [];

        $requestIdHeader = (string) config('api-authorizer.headers.request_id', 'X-REQUEST-ID');
        $requestId = $request->header($requestIdHeader);

        if ($requestId !== null && $requestId !== '') {
            $headers[$requestIdHeader] = $requestId;
        }

        return response()->json([
            'success' => false,
            'status_code' => (int) config("api-authorizer.error_codes.{$exception->errorCode}", 1003),
            'message' => $exception->getMessage(),
            'errors' => ['code' => $exception->errorCode],
        ], Response::HTTP_UNAUTHORIZED, $headers);
    }

    private function logVerified(Request $request, VerifiedClient $client): void
    {
        if (! config('api-authorizer.logging.enabled', false)) {
            return;
        }

        Log::channel(config('api-authorizer.logging.channel'))->info('api-authorizer: request verified', [
            'request_id' => $client->requestId,
            'powered_by' => $client->poweredBy,
            'timestamp' => $client->timestamp,
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
        ]);
    }

    private function logFailure(Request $request, ApiAuthorizationException $exception): void
    {
        if (! config('api-authorizer.logging.enabled', false)) {
            return;
        }

        Log::channel(config('api-authorizer.logging.channel'))->warning('api-authorizer: request rejected', [
            'request_id' => $request->header((string) config('api-authorizer.headers.request_id', 'X-REQUEST-ID')),
            'powered_by' => $request->header((string) config('api-authorizer.headers.powered_by', 'X-POWERED-BY')),
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
            'error' => $exception->errorCode,
        ]);
    }
}
