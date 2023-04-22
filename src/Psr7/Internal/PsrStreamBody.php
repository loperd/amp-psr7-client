<?php

namespace Loper\AmpPsr7Client\Psr7\Internal;

use Amp;
use Amp\ByteStream\ReadableStream;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrStreamBody implements Amp\Http\Client\HttpContent
{
    public function __construct(
        private readonly StreamInterface $stream,
        private readonly ?string $contentType,
    ) {
    }

    public function getContent(): ReadableStream
    {
        return new PsrInputStream($this->stream);
    }

    public function getContentLength(): ?int
    {
        return $this->stream->getSize() ?? -1;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}
