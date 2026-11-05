<?php declare(strict_types=1);

namespace Tests\Unit\Http;

use Concept\Core\Events\Http\RequestHandled;
use Concept\Core\Http\HttpRunner;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use League\Route\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingEventDispatcher;

final class HttpRunnerTest extends TestCase
{
    public function testRunDispatchesRouterAndEmitsRequestHandled(): void
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

    public function testRunWorksWithoutEventDispatcher(): void
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
}
