<?php declare(strict_types=1);

namespace Tests\Integration\Http\Routing;

use Concept\Core\Events\Http\RouteHandlerInvoked;
use Concept\Core\Events\Http\RouteInterceptorInvoked;
use Concept\Core\Http\Contracts\RouteInterceptorInterface;
use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\Request\RequestContext;
use Concept\Core\Http\Routing\Exception\UnresolvedArgumentException;
use Concept\Core\Http\Routing\Resolvers\RouteParameterArgumentResolver;
use Concept\Core\Http\Routing\Resolvers\ServerRequestArgumentResolver;
use Concept\Core\Http\Routing\RouteStrategy;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use League\Container\Container;
use League\Route\Router;
use League\Route\Route;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;
use Tests\Support\RecordingEventDispatcher;

final class RouteStrategyTest extends TestCase
{
    public function testDispatchedRouteInjectsRouteParameterIntoHandler(): void
    {
        $router = $this->createRouter();

        $router->get('/users/{id}', function (string $id): ResponseInterface {
            return new Response\TextResponse($id);
        });

        $response = $router->dispatch($this->request('GET', '/users/42'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('42', (string) $response->getBody());
    }

    public function testDispatchedRouteEnrichesRequestWithRouteAttributes(): void
    {
        $router = $this->createRouter();

        $router->get('/users/{id}', function (ServerRequestInterface $request): ResponseInterface {
            return new Response\TextResponse((string) $request->getAttribute('id'));
        });

        $response = $router->dispatch($this->request('GET', '/users/42'));

        $this->assertSame('42', (string) $response->getBody());
    }

    public function testRequestContextReceivesEnrichedRequestAndIsNotResetByStrategy(): void
    {
        $requestContext = new RequestContext();
        $router = $this->createRouter($requestContext);

        $router->get('/users/{id}', function (ServerRequestInterface $request) use ($requestContext): ResponseInterface {
            $this->assertSame('42', $requestContext->current()?->getAttribute('id'));
            $this->assertSame($requestContext->current(), $request);

            return new Response\EmptyResponse();
        });

        $router->dispatch($this->request('GET', '/users/42'));

        $this->assertSame('42', $requestContext->current()?->getAttribute('id'));
    }

    public function testHandlerMustReturnResponseInterface(): void
    {
        $router = $this->createRouter();

        $router->get('/broken', static function (): null {
            return null;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Route handler must return an instance of Psr\Http\Message\ResponseInterface.',
        );

        $router->dispatch($this->request('GET', '/broken'));
    }

    public function testUnresolvedRequiredParameterThrowsBeforeHandler(): void
    {
        $router = $this->createRouter();

        $router->get('/broken', static function (stdClass $missing): ResponseInterface {
            return new Response\EmptyResponse();
        });

        $this->expectException(UnresolvedArgumentException::class);
        $this->expectExceptionMessage(
            'Cannot resolve parameter $missing of type stdClass: no ArgumentResolver supported it.',
        );

        $router->dispatch($this->request('GET', '/broken'));
    }

    public function testUnresolvedNullableParameterReceivesNull(): void
    {
        $router = $this->createRouter();

        $router->get('/ok', function (?stdClass $missing): ResponseInterface {
            $this->assertNull($missing);

            return new Response\TextResponse('ok');
        });

        $response = $router->dispatch($this->request('GET', '/ok'));

        $this->assertSame('ok', (string) $response->getBody());
    }

    public function testUnresolvedParameterWithDefaultUsesDefault(): void
    {
        $router = $this->createRouter();

        $router->get('/ok', static function (string $name = 'guest'): ResponseInterface {
            return new Response\TextResponse($name);
        });

        $response = $router->dispatch($this->request('GET', '/ok'));

        $this->assertSame('guest', (string) $response->getBody());
    }

    public function testInterceptorRunsBeforeRouteHandler(): void
    {
        $order = [];
        $interceptor = new class($order) implements RouteInterceptorInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}

            public function intercept(Route $route, ServerRequestInterface $request): void
            {
                $this->order[] = 'interceptor';
            }
        };

        $router = $this->createRouter(interceptors: [$interceptor]);

        $router->get('/users/{id}', function () use (&$order): ResponseInterface {
            $order[] = 'handler';

            return new Response\EmptyResponse();
        });

        $router->dispatch($this->request('GET', '/users/42'));

        $this->assertSame(['interceptor', 'handler'], $order);
    }

    public function testInvalidInterceptorFactoryThrowsRuntimeException(): void
    {
        $strategy = new RouteStrategy(
            interceptors: [static fn(): stdClass => new stdClass()],
        );
        $strategy->setContainer(new Container());

        $router = new Router();
        $router->setStrategy($strategy);
        $router->get('/users/{id}', static fn(): ResponseInterface => new Response\EmptyResponse());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Route interceptor factory must return an instance of Concept\Core\Http\Contracts\RouteInterceptorInterface',
        );

        $router->dispatch($this->request('GET', '/users/42'));
    }

    public function testDispatchesRouteLifecycleEventsWhenEventDispatcherIsConfigured(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $interceptor = new class implements RouteInterceptorInterface {
            public function intercept(Route $route, ServerRequestInterface $request): void {}
        };

        $router = $this->createRouter(
            interceptors: [$interceptor],
            dispatcher: $dispatcher,
        );

        $router->get('/users/{id}', static fn(): ResponseInterface => new Response\EmptyResponse());

        $router->dispatch($this->request('GET', '/users/42'));

        $this->assertCount(2, $dispatcher->events);
        $this->assertInstanceOf(RouteInterceptorInvoked::class, $dispatcher->events[0]);
        $this->assertInstanceOf(RouteHandlerInvoked::class, $dispatcher->events[1]);
    }

    /**
     * @param list<RouteInterceptorInterface> $interceptors
     */
    private function createRouter(
        ?RequestContext $requestContext = null,
        array $interceptors = [],
        ?EventDispatcherInterface $dispatcher = null,
    ): Router {
        $container = new Container();

        if ($requestContext instanceof RequestContext) {
            $container->add(RequestContextInterface::class, fn(): RequestContext => $requestContext)
                ->setShared(true);
        }

        $strategy = new RouteStrategy(
            resolvers: [
                new RouteParameterArgumentResolver(),
                new ServerRequestArgumentResolver(),
            ],
            interceptors: $interceptors,
            dispatcher: $dispatcher,
        );
        $strategy->setContainer($container);

        $router = new Router();
        $router->setStrategy($strategy);

        return $router;
    }

    private function request(string $method, string $path): ServerRequest
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(new Uri('http://example.com' . $path));
    }
}
