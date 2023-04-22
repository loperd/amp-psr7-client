<?php

namespace Loper\AmpPsr7Client\Psr7\Internal;

use Psr\Http\Message\StreamInterface;
use Amp\ByteStream\ReadableStream;
use Amp\Future;
use Amp;

/**
 * @internal
 */
final class PsrMessageStream implements StreamInterface
{
    private const DEFAULT_TIMEOUT = 5000;
    private string $buffer = '';
    private bool $isEof = false;

    public function __construct(private ?ReadableStream $stream, private readonly int $timeout = self::DEFAULT_TIMEOUT)
    {
    }

    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        $this->stream = null;
    }

    public function eof(): bool
    {
        return $this->isEof;
    }

    public function tell(): int
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function rewind(): void
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new \RuntimeException("Source stream is not writable");
    }

    public function getMetadata($key = null): ?array
    {
        return $key === null ? [] : null;
    }

    public function detach(): void
    {
        $this->stream = null;
    }

    public function isReadable(): bool
    {
        return $this->stream !== null;
    }

    public function read($length): string
    {
        while (!$this->isEof && \strlen($this->buffer) < $length) {
            $this->buffer .= $this->readFromStream();
        }

        $data = \substr($this->buffer, 0, $length);
        $this->buffer = \substr($this->buffer, \strlen($data));

        return $data;
    }

    public function getContents(): string
    {
        while (!$this->isEof) {
            $this->buffer .= $this->readFromStream();
        }

        return $this->buffer;
    }

    private function readFromStream(): string
    {
        $data = Future\awaitFirst([Amp\async(
            static fn () => $this->getOpenStream()->read()),
            new Amp\TimeoutCancellation($this->timeout)
        ]);

        if ($data === null) {
            $this->isEof = true;

            return '';
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!\is_string($data)) {
            throw new \RuntimeException("Invalid data received from stream");
        }

        return $data;
    }

    private function getOpenStream(): ReadableStream
    {
        if ($this->stream === null) {
            throw new \RuntimeException("Stream is closed");
        }

        return $this->stream;
    }
}
