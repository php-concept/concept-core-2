<?php declare(strict_types=1);

namespace Concept\Core\Http;

use Concept\Core\Events\Http\RequestHandled;
use Concept\Core\Http\Contracts\RequestContextInterface;
use League\Route\Router;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HttpRunner
{
    public function __construct(
        private readonly Router $router,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?RequestContextInterface $requestContext = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $startedAt = microtime(true);
        $memoryStart = memory_get_usage(false);

        $this->requestContext?->set($request);

        try {
            $response = $this->router->dispatch($request);
            $this->requestContext?->reset();

            return $response;
        } finally {
            if ($this->dispatcher instanceof EventDispatcherInterface) {
                $this->dispatcher->dispatch(new RequestHandled(
                    method: $request->getMethod(),
                    path: $request->getUri()->getPath(),
                    memoryStart: $memoryStart,
                    memoryEnd: memory_get_usage(false),
                    memoryPeak: memory_get_peak_usage(false),
                    startedAt: $startedAt,
                    duration: microtime(true) - $startedAt,
                ));
            }
        }
    }
}
