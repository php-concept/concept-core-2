<?php declare(strict_types=1);

namespace Concept\Core;

use Concept\Core\Container\ContainerDependency;
use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\HttpRunner;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequestFactory;
use League\Container\Container;
use League\Container\ReflectionContainer;
use League\Container\ServiceProvider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class App
{
    private const string ERR_HTTP_KERNEL_NOT_REGISTERED =
        'HTTP kernel must be registered before running the application.';
    private const string ERR_IS_NOT_PROVIDER_INSTANCE =
        'Item #%s in providers list should implement ServiceProviderInterface';

    private Container $container;

    private function __construct()
    {
        $this->container = new Container();
        $this->container->delegate(new ReflectionContainer(cacheResolutions: false));
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

    /**
     * Builds a request from SAPI globals, clears any previous RequestContext, and stores the new one.
     *
     * @param array<mixed>|null $server
     * @param array<mixed>|null $query
     * @param array<mixed>|null $body
     * @param array<mixed>|null $cookies
     * @param array<mixed>|null $files
     */
    public function requestFromGlobals(
        ?array $server = null,
        ?array $query = null,
        ?array $body = null,
        ?array $cookies = null,
        ?array $files = null,
    ): ServerRequestInterface {
        $request = ServerRequestFactory::fromGlobals(
            $server ?? $_SERVER,
            $query ?? $_GET,
            $body ?? $_POST,
            $cookies ?? $_COOKIE,
            $files ?? $_FILES,
        );

        $context = ContainerDependency::getOrNull($this->container, RequestContextInterface::class);
        $context?->reset();
        $context?->set($request);

        return $request;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Interfaces are not auto-wired by ReflectionContainer; this signals HttpKernelServiceProvider.
        if (!$this->container->has(RequestContextInterface::class)) {
            throw new RuntimeException(self::ERR_HTTP_KERNEL_NOT_REGISTERED);
        }

        return ContainerDependency::get($this->container, HttpRunner::class)->handle($request);
    }
}
