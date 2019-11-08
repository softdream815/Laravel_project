<?php

namespace Laravel\Passport\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Exceptions\MissingScopeException;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\StreamFactory;
use Zend\Diactoros\UploadedFileFactory;

class CheckClientCredentialsForAnyScope
{
    /**
     * The Resource Server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $server;

    /**
     * Client Repository.
     *
     * @var \Laravel\Passport\ClientRepository
     */
    protected $repository;

    /**
     * Create a new middleware instance.
     *
     * @param  \League\OAuth2\Server\ResourceServer  $server
     * @param  \Laravel\Passport\ClientRepository  $repository
     * @return void
     */
    public function __construct(ResourceServer $server, ClientRepository $repository)
    {
        $this->server = $server;
        $this->repository = $repository;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$scopes
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException|\Laravel\Passport\Exceptions\MissingScopeException
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        $psr = (new PsrHttpFactory(
            new ServerRequestFactory,
            new StreamFactory,
            new UploadedFileFactory,
            new ResponseFactory
        ))->createRequest($request);

        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new AuthenticationException;
        }

        if ($this->validate($psr, $scopes)) {
            return $next($request);
        }

        throw new MissingScopeException($scopes);
    }

    /**
     * Validate the scopes and token on the incoming request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $psr
     * @param  array  $scopes
     * @return bool
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function validate($psr, $scopes)
    {
        $client = $this->repository->find($psr->getAttribute('oauth_client_id'));

        if (! $client || $client->firstParty()) {
            throw new AuthenticationException;
        }

        if (in_array('*', $tokenScopes = $psr->getAttribute('oauth_scopes'))) {
            return true;
        }

        foreach ($scopes as $scope) {
            if (in_array($scope, $tokenScopes)) {
                return true;
            }
        }

        return false;
    }
}
