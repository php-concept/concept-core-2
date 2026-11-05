<?php declare(strict_types=1);

namespace Concept\Core;

use Concept\Core\Http\HttpRunner;
use InvalidArgumentException;
use League\Container\Container;
use League\Container\ReflectionContainer;
use League\Container\ServiceProvider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class App
{
    private const string ERR_RUNNER_AND_REQUEST_NOT_REGISTERED =
        'HttpRunner and ServerRequestInterface must be registered before running the application.';
    private const string ERR_IS_NOT_PROVIDER_INSTANCE =
        'Item #%s in providers list should implements ServiceProviderInterface';

    private Container $container;

    private function __construct()
    {
        $this->container = new Container();
        $this->container->delegate(new ReflectionContainer(cacheResolutions: true));
        $this->container->add(ContainerInterface::class, $this->container)->setShared(true);
        $this->container->add(App::class, $this)->setShared(true);
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
        if (!$this->container->has(HttpRunner::class) || !$this->container->has(ServerRequestInterface::class)) {
            throw new RuntimeException(self::ERR_RUNNER_AND_REQUEST_NOT_REGISTERED);
        }

        /** @var HttpRunner $runner */
        $runner = $this->container->get(HttpRunner::class);
        /** @var ServerRequestInterface $request */
        $request = $this->container->get(ServerRequestInterface::class);

        return $runner->handle($request);
    }
}
