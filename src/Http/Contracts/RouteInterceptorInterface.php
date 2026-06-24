<?php declare(strict_types=1);

namespace Concept\Core\Http\Contracts;

use League\Route\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Pre-handler hook invoked by {@see \Concept\Core\Http\Routing\RouteStrategy} for each matched route.
 * Applications throw their own exceptions; core does not define authorization types.
 */
interface RouteInterceptorInterface
{
    public function intercept(Route $route, ServerRequestInterface $request): void;
}
