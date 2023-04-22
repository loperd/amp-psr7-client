<?php

namespace Loper\AmpPsr7Client\Psr7;

use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Response;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Amp;

final class PsrHttpClient
{
    private HttpClient $httpClient;

    private PsrAdapter $psrAdapter;

    public function __construct(HttpClient $client, PsrAdapter $psrAdapter)
    {
        $this->httpClient = $client;
        $this->psrAdapter = $psrAdapter;
    }

    /**
     * @param PsrRequest $psrRequest
     * @param null|Cancellation $cancellationToken
     *
     * @return Future<PsrResponse>
     */
    public function request(PsrRequest $psrRequest, ?Amp\Cancellation $cancellationToken = null): Amp\Future
    {
        return Amp\async(function () use ($psrRequest, $cancellationToken) {
            $request = $this->psrAdapter->fromPsrRequest($psrRequest);

            /** @var Response $response */
            $response = $this->httpClient->request($request, $cancellationToken);

            /** @var PsrResponse $psrResponse */
            $psrResponse = $this->psrAdapter->toPsrResponse($response)->await();

            return $psrResponse;
        });
    }
}
