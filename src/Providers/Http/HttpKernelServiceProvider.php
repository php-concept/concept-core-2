<?php declare(strict_types=1);

namespace Concept\Core\Providers\Http;

use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\Contracts\RouteInterceptorInterface;
use Concept\Core\Http\HttpRunner;
use Concept\Core\Http\Request\RequestContext;
use Concept\Core\Http\Routing\RouteStrategy;
use Closure;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequestFactory;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class HttpKernelServiceProvider extends AbstractServiceProvider
{
    private const string ERR_ROUTES_NOT_FOUND = 'Routes file not found at: %s';

    /**
     * @param array<string> $routePaths
     * @param array<ArgumentResolverInterface> $resolvers Ordered argument resolver chain
     * @param array<RouteInterceptorInterface|Closure(ContainerInterface): mixed> $interceptors
     * @param MiddlewareInterface|Closure(ContainerInterface): mixed|null $notFoundMiddleware
     */
    public function __construct(
        private readonly array $routePaths,
        private readonly array $resolvers = [],
        private readonly array $interceptors = [],
        private readonly MiddlewareInterface|Closure|null $notFoundMiddleware = null,
    ) {}

    public function provides(string $id): bool
    {
        $services = [
            ServerRequestInterface::class,
            RequestContextInterface::class,
            Router::class,
            HttpRunner::class,
        ];

        return in_array($id, $services);
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(ServerRequestInterface::class, function() {
            return ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        })->setShared(true);

        $container->add(RequestContextInterface::class, fn() => new RequestContext())->setShared(true);

        $container->add(Router::class, function() use ($container) {
            $router = new Router();

            $dispatcher = $container->has(EventDispatcherInterface::class)
                ? $container->get(EventDispatcherInterface::class)
                : null;

            $strategy = new RouteStrategy(
                $this->resolvers,
                $this->interceptors,
                $dispatcher instanceof EventDispatcherInterface ? $dispatcher : null,
                $this->notFoundMiddleware,
            );
            $strategy->setContainer($container);
            $router->setStrategy($strategy);
            $this->registerRoutes($router, $this->routePaths);

            return $router;
        })->setShared(true);

        $container->add(HttpRunner::class, function() use ($container) {
            $router = $container->get(Router::class);
            if (!$router instanceof Router) {
                throw new InvalidArgumentException('Router service must resolve to ' . Router::class);
            }

            $dispatcher = $container->has(EventDispatcherInterface::class)
                ? $container->get(EventDispatcherInterface::class)
                : null;

            return new HttpRunner(
                $router,
                $dispatcher instanceof EventDispatcherInterface ? $dispatcher : null,
            );
        })->setShared(true);
    }

    /**
     * @param Router $router
     * @param array<string> $routePaths
     */
    private function registerRoutes(Router $router, array $routePaths): void
    {
        $loader = function(Router $router, string $filePath) {
            require $filePath;
        };

        foreach ($routePaths as $routesFileName) {
            if (!file_exists($routesFileName)) {
                throw new InvalidArgumentException(sprintf(self::ERR_ROUTES_NOT_FOUND, $routesFileName));
            }

            $loader($router, $routesFileName);
        }
    }
}
