<?php declare(strict_types=1);

namespace Tests\Unit\Http\Routing\Resolvers;

use Concept\Core\Http\Routing\Resolvers\ServerRequestArgumentResolver;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionParameter;

final class ServerRequestArgumentResolverTest extends TestCase
{
    private ServerRequestArgumentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ServerRequestArgumentResolver();
    }

    public function testSupportsReturnsTrueWhenParameterTypeIsServerRequestInterface(): void
    {
        $parameter = $this->parameterWithType(ServerRequestInterface::class);

        // $vars is ignored by this resolver — supports() only inspects the type hint.
        $result = $this->resolver->supports($parameter, []);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsTrueWhenParameterTypeIsServerRequestImplementation(): void
    {
        $parameter = $this->parameterWithType(ServerRequest::class);

        $result = $this->resolver->supports($parameter, []);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseWhenParameterTypeIsString(): void
    {
        $parameter = $this->parameterWithType('string');

        $result = $this->resolver->supports($parameter, []);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWhenParameterHasNoTypeHint(): void
    {
        $parameter = $this->parameterWithType('untyped');

        $result = $this->resolver->supports($parameter, []);

        $this->assertFalse($result);
    }

    public function testResolveReturnsTheSameRequestInstance(): void
    {
        $parameter = $this->parameterWithType(ServerRequestInterface::class);
        $request = new ServerRequest();

        $value = $this->resolver->resolve($parameter, $request, []);

        $this->assertSame($request, $value);
    }

    private function parameterWithType(string $type): ReflectionParameter
    {
        return match ($type) {
            ServerRequestInterface::class => (new ReflectionFunction(
                function (ServerRequestInterface $request): void {},
            ))->getParameters()[0],
            ServerRequest::class => (new ReflectionFunction(
                function (ServerRequest $request): void {},
            ))->getParameters()[0],
            'string' => (new ReflectionFunction(
                function (string $id): void {},
            ))->getParameters()[0],
            'untyped' => (new ReflectionFunction(
                function ($request): void {},
            ))->getParameters()[0],
            default => throw new InvalidArgumentException("Unknown type: {$type}"),
        };
    }
}
