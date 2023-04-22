<?php

declare(strict_types=1);

namespace Loper\AmpPsr7Client;

use Amp\Http\Client\HttpClient;
use Loper\AmpPsr7Client\Psr7\PsrAdapter;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Client implements ClientInterface
{
    public function __construct(private readonly HttpClient $httpClient, private readonly PsrAdapter $psrAdapter)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $future = new Psr7AmpHttpFuture(function() use ($request) {
            $response = $this->httpClient->request($this->psrAdapter->fromPsrRequest($request));

            return $this->psrAdapter->toPsrResponse($response)->await();
        });

        return $future->wait();
    }

    public function sendAsyncRequest(RequestInterface $request): Psr7AmpHttpFuture
    {
        return new Psr7AmpHttpFuture(function() use ($request) {
            $response = $this->httpClient->request($this->psrAdapter->fromPsrRequest($request));

            return $this->psrAdapter->toPsrResponse($response)->await();
        });
    }
}