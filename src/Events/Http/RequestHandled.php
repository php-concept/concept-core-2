<?php declare(strict_types=1);

namespace Concept\Core\Events\Http;

final readonly class RequestHandled
{
    public function __construct(
        public string $method,
        public string $path,
        public int $memoryStart,
        public int $memoryEnd,
        public int $memoryPeak,
        public float $startedAt,
        public float $duration,
    ) {}
}
