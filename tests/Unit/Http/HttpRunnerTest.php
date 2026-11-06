<?php declare(strict_types=1);

namespace Tests\Unit\Http;

use Concept\Core\Events\Http\RequestHandled;
use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\HttpRunner;
use Concept\Core\Http\Request\RequestContext;
use Concept\Core\Http\Routing\Resolvers\RouteParameterArgumentResolver;
use Concept\Core\Http\Routing\Resolvers\ServerRequestArgumentResolver;
use Concept\Core\Http\Routing\RouteStrategy;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use League\Container\Container;
use League\Route\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\Support\RecordingEventDispatcher;

final class HttpRunnerTest extends TestCase
{
    public function testHandleDispatchesRouterAndEmitsRequestHandled(): void
    {
        $router = new Router();
        $router->get('/ping', static fn(): TextResponse => new TextResponse('pong'));

        $dispatcher = new RecordingEventDispatcher();
        $runner = new HttpRunner($router, $dispatcher);

        $response = $runner->handle(
            (new ServerRequest())
                ->withMethod('GET')
                ->withUri(new Uri('http://example.com/ping')),
        );

        $this->assertSame('pong', (string) $response->getBody());

        $events = array_values(array_filter(
            $dispatcher->events,
            static fn(object $event): bool => $event instanceof RequestHandled,
        ));
        $this->assertCount(1, $events);
        $this->assertSame('GET', $events[0]->method);
        $this->assertSame('/ping', $events[0]->path);
    }

    public function testHandleWorksWithoutEventDispatcher(): void
    {
        $router = new Router();
        $router->get('/ping', static fn(): TextResponse => new TextResponse('pong'));

        $response = (new HttpRunner($router))->handle(
            (new ServerRequest())
                ->withMethod('GET')
                ->withUri(new Uri('http://example.com/ping')),
        );

        $this->assertSame('pong', (string) $response->getBody());
    }

    public function testHandleSetsRequestContextForMiddlewareAndResetsAfterwards(): void
    {
        $requestContext = new RequestContext();
        $probe = new class {
            public mixed $seenDuringMiddleware = null;
        };

        $router = $this->createRouterWithStrategy($requestContext);
        $router->middleware(new class($requestContext, $probe) implements MiddlewareInterface {
            public function __construct(
                private readonly RequestContextInterface $requestContext,
                private readonly object $probe,
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->probe->seenDuringMiddleware = $this->requestContext->current();

                return $handler->handle($request);
            }
        });
        $router->get('/ping', static fn(): TextResponse => new TextResponse('pong'));

        $request = $this->request('GET', '/ping');
        $runner = new HttpRunner($router, requestContext: $requestContext);

        $runner->handle($request);

        $this->assertSame($request, $probe->seenDuringMiddleware);
        $this->assertNull($requestContext->current());
    }

    public function testHandleUpdatesRequestContextWithEnrichedRequestDuringHandler(): void
    {
        $requestContext = new RequestContext();
        $router = $this->createRouterWithStrategy($requestContext);
        $router->get('/users/{id}', function (ServerRequestInterface $request) use ($requestContext): ResponseInterface {
            $this->assertSame('42', $requestContext->current()?->getAttribute('id'));
            $this->assertSame($request, $requestContext->current());

            return new TextResponse('ok');
        });

        $runner = new HttpRunner($router, requestContext: $requestContext);
        $runner->handle($this->request('GET', '/users/42'));

        $this->assertNull($requestContext->current());
    }

    public function testSequentialHandlesDoNotBleedRequestState(): void
    {
        $requestContext = new RequestContext();
        $router = $this->createRouterWithStrategy($requestContext);
        $router->get('/a', function () use ($requestContext): ResponseInterface {
            $this->assertSame('/a', $requestContext->current()?->getUri()->getPath());

            return new TextResponse('a');
        });
        $router->get('/b', function () use ($requestContext): ResponseInterface {
            $this->assertSame('/b', $requestContext->current()?->getUri()->getPath());
            $this->assertNull($requestContext->current()?->getAttribute('from-a'));

            return new TextResponse('b');
        });

        $runner = new HttpRunner($router, requestContext: $requestContext);

        $responseA = $runner->handle($this->request('GET', '/a')->withAttribute('from-a', true));
        $this->assertSame('a', (string) $responseA->getBody());
        $this->assertNull($requestContext->current());

        $responseB = $runner->handle($this->request('GET', '/b'));
        $this->assertSame('b', (string) $responseB->getBody());
        $this->assertNull($requestContext->current());
    }

    public function testHandleKeepsRequestContextWhenDispatchThrows(): void
    {
        $requestContext = new RequestContext();
        $router = $this->createRouterWithStrategy($requestContext);
        $router->get('/boom', static function (): ResponseInterface {
            throw new \RuntimeException('boom');
        });

        $request = $this->request('GET', '/boom');
        $runner = new HttpRunner($router, requestContext: $requestContext);

        try {
            $runner->handle($request);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame($request, $requestContext->current());
    }

    private function createRouterWithStrategy(RequestContextInterface $requestContext): Router
    {
        $container = new Container();
        $container->add(RequestContextInterface::class, fn(): RequestContextInterface => $requestContext)
            ->setShared(true);

        $strategy = new RouteStrategy(
            resolvers: [
                new RouteParameterArgumentResolver(),
                new ServerRequestArgumentResolver(),
            ],
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
