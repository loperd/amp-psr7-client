<?php

namespace Loper\AmpPsr7Client\Psr7\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrInputStream implements ReadableStream, \IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    private const DEFAULT_CHUNK_SIZE = 8192;

    private readonly DeferredFuture $onClose;

    private StreamInterface $stream;

    private int $chunkSize;

    private bool $tryRewind = true;

    public function __construct(StreamInterface $stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        if ($chunkSize < 1) {
            throw new \Error("Invalid chunk size: {$chunkSize}");
        }

        $this->onClose = new DeferredFuture;

        $this->stream = $stream;
        $this->chunkSize = $chunkSize;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if (!$this->stream->isReadable()) {
            return null;
        }

        if ($this->tryRewind) {
            $this->tryRewind = false;

            if ($this->stream->isSeekable()) {
                $this->stream->rewind();
            }
        }

        if ($this->stream->eof()) {
            return null;
        }

        return $this->stream->read($this->chunkSize);
    }

    public function close(): void
    {
        if ($this->stream->isReadable()) {
            $this->stream->close();
        }
    }

    public function isClosed(): bool
    {
        return !$this->stream->isReadable();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }
}
