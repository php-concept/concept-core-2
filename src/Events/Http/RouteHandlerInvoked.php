<?php declare(strict_types=1);

namespace Concept\Core\Events\Http;

use League\Route\Route;

final readonly class RouteHandlerInvoked
{
    public function __construct(
        public string $handler,
        public Route $route,
        public float $startedAt,
        public float $duration,
    ) {}
}
