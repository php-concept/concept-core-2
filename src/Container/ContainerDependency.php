<?php declare(strict_types=1);

namespace Concept\Core\Container;

use Concept\Core\Container\Exception\ContainerDependencyException;
use League\Container\Exception\NotFoundException as LeagueNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerDependency
{
    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return T
     */
    public static function get(ContainerInterface $container, string $interface): object
    {
        try {
            $resolved = $container->get($interface);
        } catch (NotFoundExceptionInterface | LeagueNotFoundException | ContainerExceptionInterface $exception) {
            throw ContainerDependencyException::notRegistered($interface, $exception);
        }

        if (!$resolved instanceof $interface) {
            throw ContainerDependencyException::unexpectedType($interface, $interface, $resolved);
        }

        /** @var T $resolved */
        return $resolved;
    }
}
