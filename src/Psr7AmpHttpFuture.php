<?php

declare(strict_types=1);

namespace Loper\AmpPsr7Client;

use Amp;
use Amp\Future;
use Http\Promise\Promise;

final class Psr7AmpHttpFuture implements Promise
{
    private Amp\Future $future;
    private array $chain = [];
    private string $state = self::PENDING;

    public function __construct(\Closure $callable)
    {
        $this->future = Amp\async($callable);
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null): self
    {
        $this->chain[] = [$onFulfilled, $onRejected];

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function wait($unwrap = true): mixed
    {
        return Amp\async(function() use ($unwrap) {
            try {
                $current = $this->future->await();
            } catch (\Throwable $throwable) {
                $this->state = self::REJECTED;
                if (isset($this->chain[0][1])) {
                    $this->chain[0][1]($throwable);
                }

                if ($unwrap) {
                    throw $throwable;
                }

                return null;
            }

            foreach ($this->chain as $i => [$onSuccess, $onError]) {
                try {
                    $current = $onSuccess($current);
                } catch (\Throwable $throwable) {
                    $chainKey = $i + 1;

                    $this->state = self::REJECTED;
                    if (isset($this->chain[$chainKey][1])) {
                        $this->chain[$chainKey][1]($throwable);
                    }

                    if ($unwrap) {
                        throw $throwable;
                    }

                    return null;
                }
            }

            $this->state = self::FULFILLED;
            if ($unwrap) {
                return $current;
            }

            return null;
        })->await();
    }
}