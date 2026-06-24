<?php declare(strict_types=1);

namespace Concept\Core\Http\Routing;

use Closure;
use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Concept\Core\Http\Contracts\RouteInterceptorInterface;
use League\Container\DefinitionContainerInterface;
use League\Route\Route;
use League\Route\Strategy\ApplicationStrategy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class RouteStrategy extends ApplicationStrategy
{
    /**
     * @param array<ArgumentResolverInterface> $resolvers
     * @param array<class-string<RouteInterceptorInterface>> $interceptors
     */
    public function __construct(
        private readonly array $resolvers = [],
        private readonly array $interceptors = [],
    ) {}

    public function invokeRouteCallable(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $this->runInterceptors($route, $request);

        $request = $this->prepareRequest($route, $request);
        $callable = $route->getCallable($this->getContainer());
        try {
            $reflection = $this->getReflection($callable);
            $arguments = $this->resolveArguments($reflection, $request, $route->getVars());

            if (is_array($callable)) {
                /** @var object $object */
                $object = $callable[0];
                /** @phpstan-ignore method.notFound */
                return $reflection->invokeArgs($object, $arguments);
            }

            if (is_object($callable) && !($callable instanceof Closure)) {
                /** @phpstan-ignore method.notFound */
                return $reflection->invokeArgs($callable, $arguments);
            }

            /** @phpstan-ignore-next-line */
            return $reflection->invokeArgs($arguments);
        } finally {
            // TODO: add event
        }
    }

    private function runInterceptors(Route $route, ServerRequestInterface $request): void
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        foreach ($this->interceptors as $interceptorClass) {
            /** @var RouteInterceptorInterface $interceptor */
            $interceptor = $container->get($interceptorClass);
            $interceptor->intercept($route, $request);
        }
    }

    /**
     * Prepares the request with the route variables
     *
     * @param Route $route
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    private function prepareRequest(Route $route, ServerRequestInterface $request): ServerRequestInterface
    {
        // Add route variables to the request
        foreach ($route->getVars() as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        // Refresh the request in the container
        /** @var DefinitionContainerInterface $container */
        $container = $this->getContainer();
        $container->add(ServerRequestInterface::class, $request, true);

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
     * @param array<string> $vars
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
     * @param array<string> $vars
     * @return mixed
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $vars): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($parameter)) {
                return $resolver->resolve($parameter, $request, $vars);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return null;
    }
}
