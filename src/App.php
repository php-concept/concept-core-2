<?php declare(strict_types=1);

namespace Concept\Core;

use Concept\Core\Events\Http\RequestHandled;
use InvalidArgumentException;
use League\Container\Container;
use League\Container\ReflectionContainer;
use League\Container\ServiceProvider\ServiceProviderInterface;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class App
{
    private const string ERR_ROUTER_AND_REQUEST_NOT_REGISTERED =
        'RouterInterface and ServerRequestInterface must be registered before running the application.';
    private const string ERR_IS_NOT_PROVIDER_INSTANCE =
        'Item #%s in providers list should implements ServiceProviderInterface';

    protected Container $container;

    private function __construct()
    {
        $this->container = new Container();
        $this->container->delegate(new ReflectionContainer(cacheResolutions: true));
        $this->container->add(ContainerInterface::class, fn() => $this->container)
            ->setShared(true);
        $this->container->add(App::class, fn() => $this);
    }

    public static function create(): App
    {
        return new App();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param list<ServiceProviderInterface|callable(): ServiceProviderInterface> $providers
     */
    public function registerServiceProviders(array $providers): void
    {
        foreach ($providers as $key => $entry) {
            if ($entry instanceof ServiceProviderInterface) {
                $provider = $entry;
            } elseif (is_callable($entry)) {
                $provider = $entry();
            } else {
                throw new InvalidArgumentException(sprintf(self::ERR_IS_NOT_PROVIDER_INSTANCE, $key));
            }

            $this->container->addServiceProvider($provider);
        }
    }

    public function handle(): ResponseInterface
    {
        if (!$this->container->has(Router::class) || !$this->container->has(ServerRequestInterface::class)) {
            throw new RuntimeException(self::ERR_ROUTER_AND_REQUEST_NOT_REGISTERED);
        }

        /** @var Router $router */
        $router = $this->container->get(Router::class);
        /** @var ServerRequestInterface $request */
        $request = $this->container->get(ServerRequestInterface::class);

        $dispatcher = $this->container->has(EventDispatcherInterface::class)
            ? $this->container->get(EventDispatcherInterface::class)
            : null;

        $startedAt = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            return $router->dispatch($request);
        } finally {
            if ($dispatcher instanceof EventDispatcherInterface) {
                $dispatcher->dispatch(new RequestHandled(
                    method: $request->getMethod(),
                    path: $request->getUri()->getPath(),
                    memoryStart: $memoryStart,
                    memoryEnd: memory_get_usage(true),
                    memoryPeak: memory_get_peak_usage(true),
                    startedAt: $startedAt,
                    duration: microtime(true) - $startedAt,
                ));
            }
        }
    }
}
