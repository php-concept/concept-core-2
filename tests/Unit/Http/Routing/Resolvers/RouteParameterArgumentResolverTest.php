<?php declare(strict_types=1);

namespace Tests\Unit\Http\Routing\Resolvers;

use Concept\Core\Http\Routing\Resolvers\RouteParameterArgumentResolver;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionParameter;

final class RouteParameterArgumentResolverTest extends TestCase
{
    private RouteParameterArgumentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RouteParameterArgumentResolver();
    }

    public function testSupportsReturnsTrueWhenRouteVarMatchesParameterName(): void
    {
        $parameter = $this->parameterNamed('id');

        $result = $this->resolver->supports($parameter, ['id' => '42']);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseWhenRouteVarIsMissing(): void
    {
        $parameter = $this->parameterNamed('id');

        $result = $this->resolver->supports($parameter, []);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWhenRouteVarNameDiffers(): void
    {
        $parameter = $this->parameterNamed('id');

        $result = $this->resolver->supports($parameter, ['slug' => 'news']);

        $this->assertFalse($result);
    }

    public function testResolveReturnsMatchingRouteVarValue(): void
    {
        $parameter = $this->parameterNamed('id');
        $request = new ServerRequest();

        $value = $this->resolver->resolve($parameter, $request, ['id' => '42']);

        $this->assertSame('42', $value);
    }

    /**
     * ReflectionParameter names come from a real callable signature, not from PHPUnit.
     * A closure with parameter $id yields a ReflectionParameter named "id".
     */
    private function parameterNamed(string $name): ReflectionParameter
    {
        return match ($name) {
            'id' => (new ReflectionFunction(function (string $id): void {}))->getParameters()[0],
            'slug' => (new ReflectionFunction(function (string $slug): void {}))->getParameters()[0],
            default => throw new \InvalidArgumentException("Unknown parameter: {$name}"),
        };
    }
}
