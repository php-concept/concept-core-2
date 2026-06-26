<?php declare(strict_types=1);

namespace Concept\Core\Events\Http;

use League\Route\Route;

final readonly class RouteInterceptorInvoked
{
    public function __construct(
        public string $interceptorClass,
        public Route $route,
    ) {}
}
