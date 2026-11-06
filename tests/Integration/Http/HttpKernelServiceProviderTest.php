<?php declare(strict_types=1);

namespace Tests\Integration\Http;

use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\HttpRunner;
use Concept\Core\Http\Routing\Resolvers\RouteParameterArgumentResolver;
use Concept\Core\Http\Routing\Resolvers\ServerRequestArgumentResolver;
use Concept\Core\Providers\Http\HttpKernelServiceProvider;
use InvalidArgumentException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use League\Container\Container;
use League\Route\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class HttpKernelServiceProviderTest extends TestCase
{
    public function testRegisterThrowsWhenRoutesFileDoesNotExist(): void
    {
        $container = new Container();
        $missingPath = sys_get_temp_dir() . '/core-missing-routes-' . uniqid('', true) . '.php';
        $provider = new HttpKernelServiceProvider([$missingPath]);
        $provider->setContainer($container);
        $provider->register();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Routes file not found at: ' . $missingPath);

        $container->get(Router::class);
    }

    public function testRegisterBuildsRouterThatDispatchesLoadedRoutes(): void
    {
        $routesFile = $this->createRoutesFile();
        $container = new Container();
        $provider = new HttpKernelServiceProvider($routesFile, [
            new RouteParameterArgumentResolver(),
            new ServerRequestArgumentResolver(),
        ]);
        $provider->setContainer($container);
        $provider->register();

        /** @var Router $router */
        $router = $container->get(Router::class);

        $response = $router->dispatch(
            (new ServerRequest())
                ->withMethod('GET')
                ->withUri(new Uri('http://example.com/ping')),
        );

        $this->assertSame('pong', (string) $response->getBody());
    }

    public function testProvidesDeclaresHttpKernelServices(): void
    {
        $provider = new HttpKernelServiceProvider([]);

        $this->assertFalse($provider->provides(ServerRequestInterface::class));
        $this->assertTrue($provider->provides(RequestContextInterface::class));
        $this->assertTrue($provider->provides(Router::class));
        $this->assertTrue($provider->provides(HttpRunner::class));
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
}
