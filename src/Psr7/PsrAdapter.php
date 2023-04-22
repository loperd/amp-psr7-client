<?php

namespace Loper\AmpPsr7Client\Psr7;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Loper\AmpPsr7Client\Psr7\Internal\PsrInputStream;
use Loper\AmpPsr7Client\Psr7\Internal\PsrStreamBody;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactory;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\StreamInterface;
use Amp;
use Amp\Future;

final class PsrAdapter
{
    private PsrRequestFactory $requestFactory;

    private PsrResponseFactory $responseFactory;

    public function __construct(PsrRequestFactory $requestFactory, PsrResponseFactory $responseFactory)
    {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param PsrRequest $source
     *
     * @return Request
     */
    public function fromPsrRequest(PsrRequest $source): Request
    {
        $target = new Request($source->getUri(), $source->getMethod());
        $target->setHeaders($source->getHeaders());
        $target->setProtocolVersions([$source->getProtocolVersion()]);
        $target->setBody(new PsrStreamBody($source->getBody(), $this->getContentType($source)));

        return $target;
    }

    /**
     * @param PsrResponse   $source
     * @param Request       $request
     * @param Response|null $previousResponse
     *
     * @return Response
     */
    public function fromPsrResponse(
        PsrResponse $source,
        Request $request,
        ?Response $previousResponse = null
    ): Response {
        return new Response(
            $source->getProtocolVersion(),
            $source->getStatusCode(),
            $source->getReasonPhrase(),
            $source->getHeaders(),
            new PsrInputStream($source->getBody()),
            $request,
            null,
            $previousResponse
        );
    }

    /**
     * @param Request     $source
     * @param string|null $protocolVersion
     *
     * @return Future<PsrRequest>
     */
    public function toPsrRequest(
        Request $source,
        ?string $protocolVersion = null
    ): Future {
        return Amp\async(function () use ($source, $protocolVersion) {
            $target = $this->toPsrRequestWithoutBody($source, $protocolVersion);

            $this->copyToPsrStream($source->getBody()->getContent(), $target->getBody())->await();

            return $target;
        });
    }

    /**
     * @param Response $response
     *
     * @return Future<PsrResponse>
     */
    public function toPsrResponse(Response $response): Future
    {
        $psrResponse = $this->responseFactory->createResponse($response->getStatus(), $response->getReason())
            ->withProtocolVersion($response->getProtocolVersion());

        foreach ($response->getHeaders() as $headerName => $headerValue) {
            $psrResponse = $psrResponse->withAddedHeader($headerName, $headerValue);
        }

        return Amp\async(function () use ($psrResponse, $response): ResponseInterface {
            $this->copyToPsrStream($response->getBody(), $psrResponse->getBody())->await();

            return $psrResponse;
        });
    }

    private function copyToPsrStream(ReadableStream $source, StreamInterface $target): Future
    {
        return Amp\async(static function () use ($source, $target) {
            while (null !== $data = $source->read()) {
                $target->write($data);
            }

            $target->rewind();
        });
    }

    private function toPsrRequestWithoutBody(
        Request $source,
        ?string $protocolVersion = null
    ): PsrRequest {
        $target = $this->requestFactory->createRequest($source->getMethod(), $source->getUri());

        foreach ($source->getHeaders() as $headerName => $headerValue) {
            $target = $target->withAddedHeader($headerName, $headerValue);
        }

        $protocolVersions = $source->getProtocolVersions();
        if ($protocolVersion !== null) {
            if (!\in_array($protocolVersion, $protocolVersions, true)) {
                throw new \RuntimeException(
                    "Source request doesn't support the provided HTTP protocol version: {$protocolVersion}"
                );
            }

            return $target->withProtocolVersion($protocolVersion);
        }

        if (\count($protocolVersions) === 1) {
            return $target->withProtocolVersion($protocolVersions[0]);
        }

        if (!\in_array($target->getProtocolVersion(), $protocolVersions, true)) {
            throw new HttpException(
                "Can't choose HTTP protocol version automatiAmp\asyncy: [" . \implode(', ', $protocolVersions) . ']'
            );
        }

        return $target;
    }

    public function getContentType(PsrRequest $source): ?string
    {
        foreach ($source->getHeaders() as $headerName => $headerValue) {
            if ('content-type' === \mb_strtolower($headerName)) {
                return $headerValue[0];
            }
        }

        return null;
    }
}
