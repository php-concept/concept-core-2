<?php declare(strict_types=1);

namespace Concept\Core\Http\Routing;

use Closure;
use Concept\Core\Events\Http\RouteHandlerInvoked;
use Concept\Core\Events\Http\RouteInterceptorInvoked;
use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Concept\Core\Http\Contracts\RequestContextInterface;
use Concept\Core\Http\Contracts\RouteInterceptorInterface;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Route;
use Psr\EventDispatcher\EventDispatcherInterface;
use League\Route\Strategy\ApplicationStrategy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

class RouteStrategy extends ApplicationStrategy
{
    private const string ERR_HANDLER_MUST_RETURN_RESPONSE = 'Route handler must return an instance of %s.';
    private const string ERR_FACTORY_CONTAINER = 'Container is required to invoke route factories.';
    private const string ERR_NOT_FOUND_MIDDLEWARE_INVALID = 'Not-found middleware factory must return an instance of %s, got %s.';
    private const string ERR_INTERCEPTOR_INVALID = 'Route interceptor factory must return an instance of %s, got %s.';

    private ?MiddlewareInterface $resolvedNotFoundMiddleware = null;

    /**
     * @param array<ArgumentResolverInterface> $resolvers
     * @param array<RouteInterceptorInterface|Closure(ContainerInterface): mixed> $interceptors
     * @param MiddlewareInterface|Closure(ContainerInterface): mixed|null $notFoundMiddleware
     */
    public function __construct(
        private readonly array $resolvers = [],
        private readonly array $interceptors = [],
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly MiddlewareInterface|Closure|null $notFoundMiddleware = null,
    ) {}

    public function getNotFoundDecorator(NotFoundException $exception): MiddlewareInterface
    {
        if ($this->resolvedNotFoundMiddleware instanceof MiddlewareInterface) {
            return $this->resolvedNotFoundMiddleware;
        }

        if ($this->notFoundMiddleware instanceof MiddlewareInterface) {
            $this->resolvedNotFoundMiddleware = $this->notFoundMiddleware;

            return $this->resolvedNotFoundMiddleware;
        }

        if ($this->notFoundMiddleware instanceof Closure) {
            $container = $this->getContainer();
            if (!$container instanceof ContainerInterface) {
                throw new RuntimeException(self::ERR_FACTORY_CONTAINER);
            }

            $middleware = ($this->notFoundMiddleware)($container);

            if (!$middleware instanceof MiddlewareInterface) {
                throw new RuntimeException(sprintf(
                    self::ERR_NOT_FOUND_MIDDLEWARE_INVALID,
                    MiddlewareInterface::class,
                    is_object($middleware) ? $middleware::class : get_debug_type($middleware),
                ));
            }

            $this->resolvedNotFoundMiddleware = $middleware;

            return $this->resolvedNotFoundMiddleware;
        }

        return parent::getNotFoundDecorator($exception);
    }

    public function invokeRouteCallable(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $this->runInterceptors($route, $request);

        $request = $this->prepareRequest($route, $request);
        $requestContext = $this->resolveRequestContext();
        $requestContext?->set($request);

        $callable = $route->getCallable($this->getContainer());
        $startedAt = microtime(true);
        try {
            $reflection = $this->getReflection($callable);
            /** @var array<string, mixed> $vars */
            $vars = $route->getVars();
            $arguments = $this->resolveArguments($reflection, $request, $vars);

            if (is_array($callable)) {
                /** @var object $object */
                $object = $callable[0];
                /** @phpstan-ignore method.notFound */
                return $this->assertResponse($reflection->invokeArgs($object, $arguments));
            }

            if (is_object($callable) && !($callable instanceof Closure)) {
                /** @phpstan-ignore method.notFound */
                return $this->assertResponse($reflection->invokeArgs($callable, $arguments));
            }

            /** @phpstan-ignore-next-line */
            return $this->assertResponse($reflection->invokeArgs($arguments));
        } finally {
            $this->dispatchRouteHandlerInvoked($callable, $route, $startedAt);
            $requestContext?->reset();
        }
    }

    private function resolveRequestContext(): ?RequestContextInterface
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        if (!$container->has(RequestContextInterface::class)) {
            return null;
        }

        $requestContext = $container->get(RequestContextInterface::class);

        return $requestContext instanceof RequestContextInterface ? $requestContext : null;
    }

    private function runInterceptors(Route $route, ServerRequestInterface $request): void
    {
        $container = $this->getContainer();
        if (!$container instanceof ContainerInterface) {
            throw new RuntimeException(self::ERR_FACTORY_CONTAINER);
        }

        foreach ($this->interceptors as $interceptorEntry) {
            $interceptor = $interceptorEntry instanceof Closure
                ? $interceptorEntry($container)
                : $interceptorEntry;

            if (!$interceptor instanceof RouteInterceptorInterface) {
                throw new RuntimeException(sprintf(
                    self::ERR_INTERCEPTOR_INVALID,
                    RouteInterceptorInterface::class,
                    is_object($interceptor) ? $interceptor::class : get_debug_type($interceptor),
                ));
            }

            $interceptor->intercept($route, $request);
            $this->dispatcher?->dispatch(new RouteInterceptorInvoked($interceptor::class, $route));
        }
    }

    /**
     * Enriches the request with route variables as PSR-7 attributes.
     * The enriched instance is passed to argument resolvers only — not stored in the container.
     */
    private function prepareRequest(Route $route, ServerRequestInterface $request): ServerRequestInterface
    {
        foreach ($route->getVars() as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $request;
    }

    /**
     * Creates a reflection object depending on the handler type (class method or closure)
     *
     * @param callable $callable
     * @return ReflectionFunctionAbstract
     * @throws ReflectionException
     */
    private function getReflection(callable $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        /** @phpstan-ignore-next-line */
        return new ReflectionMethod($callable, '__invoke');
    }

    /**
     * Resolves arguments for the given reflection object (Auto-wiring)
     *
     * @param ReflectionFunctionAbstract $reflection
     * @param ServerRequestInterface $request
     * @param array<string, mixed> $vars
     * @return array<mixed>
     */
    private function resolveArguments(
        ReflectionFunctionAbstract $reflection,
        ServerRequestInterface $request,
        array $vars): array
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $request, $vars);
        }

        return $arguments;
    }

    /**
     * Resolves arguments for the given reflection object
     *
     * @param ReflectionParameter $parameter
     * @param ServerRequestInterface $request
     * @param array<string, mixed> $vars
     * @return mixed
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $vars): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($parameter, $vars)) {
                return $resolver->resolve($parameter, $request, $vars);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return null;
    }

    private function assertResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        throw new RuntimeException(sprintf(self::ERR_HANDLER_MUST_RETURN_RESPONSE, ResponseInterface::class));
    }

    private function dispatchRouteHandlerInvoked(callable $callable, Route $route, float $startedAt): void
    {
        $duration = microtime(true) - $startedAt;
        $this->dispatcher?->dispatch(new RouteHandlerInvoked(
            handler: $this->describeCallable($callable),
            route: $route,
            startedAt: $startedAt,
            duration: $duration,
        ));
    }

    private function describeCallable(callable $callable): string
    {
        if ($callable instanceof Closure) {
            return 'closure';
        }

        if (is_string($callable)) {
            return $callable;
        }

        if (is_array($callable)) {
            $target = $callable[0];
            $method = $callable[1];

            return (is_object($target) ? $target::class : (string) $target) . '::' . (string) $method;
        }

        if (is_object($callable)) {
            return $callable::class . '::__invoke';
        }

        return 'callable';
    }
}
