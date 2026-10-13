<?php declare(strict_types=1);

namespace Tests\Unit\Container;

use Concept\Core\Container\ContainerDependency;
use Concept\Core\Container\Exception\ContainerDependencyException;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use stdClass;

final class ContainerDependencyTest extends TestCase
{
    public function testGetReturnsServiceWhenContainerResolvesExpectedType(): void
    {
        $expected = new Foo();
        $container = new class($expected) implements ContainerInterface {
            public function __construct(private object $service) {}

            public function get(string $id): object
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $result = ContainerDependency::get($container, FooInterface::class);

        $this->assertSame($expected, $result);
    }

    public function testGetThrowsWhenContainerCannotResolveService(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new class('Not found') extends Exception implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $this->expectException(ContainerDependencyException::class);
        $this->expectExceptionMessage('Container service (Tests\Unit\Container\FooInterface) is not registered.');

        ContainerDependency::get($container, FooInterface::class);
    }

    public function testGetThrowsResolutionFailedWhenContainerThrowsContainerException(): void
    {
        $previous = new class('factory blew up') extends Exception implements ContainerExceptionInterface {};
        $container = new class($previous) implements ContainerInterface {
            public function __construct(private Exception $previous) {}

            public function get(string $id): never
            {
                throw $this->previous;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        try {
            ContainerDependency::get($container, FooInterface::class);
            $this->fail('Expected ContainerDependencyException');
        } catch (ContainerDependencyException $exception) {
            $this->assertTrue($exception->isResolutionFailed());
            $this->assertFalse($exception->isNotRegistered());
            $this->assertSame(
                'Container service (Tests\Unit\Container\FooInterface) could not be resolved.',
                $exception->getMessage(),
            );
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testGetThrowsWhenContainerReturnsUnexpectedType(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object
            {
                return new stdClass();
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->expectException(ContainerDependencyException::class);
        $this->expectExceptionMessage(
            'Container service (Tests\Unit\Container\FooInterface) must be an instance of Tests\Unit\Container\FooInterface, stdClass given.',
        );

        ContainerDependency::get($container, FooInterface::class);
    }

    public function testGetOrNullReturnsServiceWhenRegistered(): void
    {
        $expected = new Foo();
        $container = new class($expected) implements ContainerInterface {
            public function __construct(private object $service) {}

            public function get(string $id): object
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->assertSame($expected, ContainerDependency::getOrNull($container, FooInterface::class));
    }

    public function testGetOrNullReturnsNullWhenHasIsFalse(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new class('Not found') extends Exception implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $this->assertNull(ContainerDependency::getOrNull($container, FooInterface::class));
    }

    public function testGetOrNullThrowsWhenHasIsTrueButGetNotFound(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new class('Not found') extends Exception implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->expectException(ContainerDependencyException::class);
        $this->expectExceptionMessage('Container service (Tests\Unit\Container\FooInterface) is not registered.');

        ContainerDependency::getOrNull($container, FooInterface::class);
    }

    public function testGetOrNullThrowsWhenResolutionFails(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new class('circular dependency') extends Exception implements ContainerExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->expectException(ContainerDependencyException::class);
        $this->expectExceptionMessage('Container service (Tests\Unit\Container\FooInterface) could not be resolved.');

        ContainerDependency::getOrNull($container, FooInterface::class);
    }

    public function testGetOrNullPropagatesConstructorFailure(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new RuntimeException('constructor failed');
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('constructor failed');

        ContainerDependency::getOrNull($container, FooInterface::class);
    }

    public function testGetOrNullThrowsWhenContainerReturnsUnexpectedType(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object
            {
                return new stdClass();
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->expectException(ContainerDependencyException::class);
        $this->expectExceptionMessage(
            'Container service (Tests\Unit\Container\FooInterface) must be an instance of Tests\Unit\Container\FooInterface, stdClass given.',
        );

        ContainerDependency::getOrNull($container, FooInterface::class);
    }
}

interface FooInterface {}

final class Foo implements FooInterface {}
