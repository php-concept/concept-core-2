<?php declare(strict_types=1);

namespace Tests\Unit\Container;

use Concept\Core\Container\ContainerDependency;
use Concept\Core\Container\Exception\ContainerDependencyException;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
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
}

interface FooInterface {}

final class Foo implements FooInterface {}
