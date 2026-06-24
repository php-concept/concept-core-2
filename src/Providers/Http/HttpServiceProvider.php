<?php declare(strict_types=1);

namespace Concept\Core\Providers\Http;

use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Concept\Core\Http\Contracts\RouteInterceptorInterface;
use Concept\Core\Http\Routing\RouteStrategy;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequestFactory;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Route\Router;
use Psr\Http\Message\ServerRequestInterface;

class HttpServiceProvider extends AbstractServiceProvider
{
    private const string ERR_ROUTES_NOT_FOUND = 'Routes file not found at: %s';

    /**
     * @param array<string> $routePaths
     * @param array<ArgumentResolverInterface> $resolvers
     * @param array<class-string<RouteInterceptorInterface>> $interceptors
     */
    public function __construct(
        private readonly array $routePaths,
        private readonly array $resolvers = [],
        private readonly array $interceptors = []
    ) {}

    public function provides(string $id): bool
    {
        $services = [
            ServerRequestInterface::class,
            Router::class,
        ];

        return in_array($id, $services);
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(ServerRequestInterface::class, function () {
            return ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        })->setShared(true);

        $container->add(Router::class, function () use ($container) {
            $router = new Router();

            $strategy = new RouteStrategy($this->resolvers, $this->interceptors);
            $strategy->setContainer($container);
            $router->setStrategy($strategy);
            $this->registerRoutes($router, $this->routePaths);

            return $router;
        })->setShared(true);
    }

    /**
     * @param Router $router
     * @param array<string> $routePaths
     * @return void
     */
    private function registerRoutes(Router $router, array $routePaths): void
    {
        $loader = function (Router $router, string $filePath) {
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
