<?php declare(strict_types=1);

namespace Tests\Integration;

use Concept\Core\App;
use Concept\Core\Events\Http\RequestHandled;
use Concept\Core\Http\HttpRunner;
use Concept\Core\Http\Routing\Resolvers\RouteParameterArgumentResolver;
use Concept\Core\Http\Routing\Resolvers\ServerRequestArgumentResolver;
use Concept\Core\Providers\Http\HttpKernelServiceProvider;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Route\Router;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;
use Tests\Support\RecordingEventDispatcher;

final class AppTest extends TestCase
{
    public function testHandleThrowsWhenBootstrapIsIncomplete(): void
    {
        $app = App::create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'HttpRunner and ServerRequestInterface must be registered before running the application.',
        );

        $app->handle();
    }

    public function testHandleThrowsWhenRequestIsNotRegistered(): void
    {
        $app = App::create();
        $container = $app->getContainer();
        $container->add(
            HttpRunner::class,
            fn(): HttpRunner => new HttpRunner(new Router()),
        )->setShared(true);

        $this->expectException(RuntimeException::class);

        $app->handle();
    }

    public function testHandleDispatchesRegisteredRouterAndReturnsResponse(): void
    {
        $routesFile = $this->createRoutesFile();
        $app = App::create();
        $app->registerServiceProviders([
            new HttpKernelServiceProvider($routesFile, [
                new RouteParameterArgumentResolver(),
                new ServerRequestArgumentResolver(),
            ]),
        ]);

        $app->getContainer()->add(
            ServerRequestInterface::class,
            fn(): ServerRequest => $this->createRequest('GET', '/ping'),
        )->setShared(true);

        $response = $app->handle();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pong', (string) $response->getBody());
    }

    public function testHandleDispatchesRequestHandledEventWhenDispatcherIsRegistered(): void
    {
        $routesFile = $this->createRoutesFile();
        $dispatcher = new RecordingEventDispatcher();
        $app = App::create();
        $container = $app->getContainer();
        $container->add(EventDispatcherInterface::class, fn(): RecordingEventDispatcher => $dispatcher)
            ->setShared(true);

        $app->registerServiceProviders([
            new HttpKernelServiceProvider($routesFile, [
                new RouteParameterArgumentResolver(),
                new ServerRequestArgumentResolver(),
            ]),
        ]);

        $container->add(
            ServerRequestInterface::class,
            fn(): ServerRequest => $this->createRequest('GET', '/ping'),
        )->setShared(true);

        $app->handle();

        $requestHandledEvents = array_values(array_filter(
            $dispatcher->events,
            static fn(object $event): bool => $event instanceof RequestHandled,
        ));

        $this->assertCount(1, $requestHandledEvents);
        $event = $requestHandledEvents[0];
        $this->assertSame('GET', $event->method);
        $this->assertSame('/ping', $event->path);
        $this->assertGreaterThanOrEqual(0, $event->duration);
    }

    public function testRegisterServiceProvidersAcceptsProviderInstance(): void
    {
        $app = App::create();
        $provider = new EmptyServiceProvider();

        $app->registerServiceProviders([$provider]);

        $this->assertTrue($app->getContainer()->get(EmptyServiceProvider::class));
    }

    public function testRegisterServiceProvidersAcceptsCallableReturningProvider(): void
    {
        $app = App::create();

        $app->registerServiceProviders([
            fn(): EmptyServiceProvider => new EmptyServiceProvider(),
        ]);

        $this->assertTrue($app->getContainer()->get(EmptyServiceProvider::class));
    }

    public function testRegisterServiceProvidersThrowsWhenEntryIsInvalid(): void
    {
        $app = App::create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item #0 in providers list should implements ServiceProviderInterface');

        $app->registerServiceProviders([new stdClass()]);
    }

    /**
     * @return non-empty-list<string>
     */
    private function createRoutesFile(): array
    {
        $routesFile = tempnam(sys_get_temp_dir(), 'core-routes-');
        $this->assertNotFalse($routesFile);

        file_put_contents($routesFile, <<<'PHP'
            <?php declare(strict_types=1);

            use Laminas\Diactoros\Response;

            $router->get('/ping', static fn(): Response\TextResponse => new Response\TextResponse('pong'));
            PHP);

        return [$routesFile];
    }

    private function createRequest(string $method, string $path): ServerRequest
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(new Uri('http://example.com' . $path));
    }
}

final class EmptyServiceProvider extends AbstractServiceProvider
{
    public function provides(string $id): bool
    {
        return $id === self::class;
    }

    public function register(): void
    {
        $this->getContainer()->add(self::class, fn(): true => true)->setShared(true);
    }
}
