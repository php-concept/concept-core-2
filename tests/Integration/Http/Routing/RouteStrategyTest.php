<?php declare(strict_types=1);

namespace Tests\Integration\Http\Routing;

use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\Request\RequestContext;
use Concept\Core\Http\Routing\Resolvers\RouteParameterArgumentResolver;
use Concept\Core\Http\Routing\Resolvers\ServerRequestArgumentResolver;
use Concept\Core\Http\Routing\RouteStrategy;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use League\Container\Container;
use League\Route\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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

    public function testRequestContextReceivesEnrichedRequestAndResetsAfterHandler(): void
    {
        $requestContext = new RequestContext();
        $router = $this->createRouter($requestContext);

        $router->get('/users/{id}', function (ServerRequestInterface $request) use ($requestContext): ResponseInterface {
            $this->assertSame('42', $requestContext->current()?->getAttribute('id'));
            $this->assertSame($requestContext->current(), $request);

            return new Response\EmptyResponse();
        });

        $router->dispatch($this->request('GET', '/users/42'));

        $this->assertNull($requestContext->current());
    }

    private function createRouter(?RequestContext $requestContext = null): Router
    {
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
