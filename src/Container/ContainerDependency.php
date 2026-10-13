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
        } catch (NotFoundExceptionInterface | LeagueNotFoundException $exception) {
            throw ContainerDependencyException::notRegistered($interface, $exception);
        } catch (ContainerExceptionInterface $exception) {
            throw ContainerDependencyException::resolutionFailed($interface, $exception);
        }

        if (!$resolved instanceof $interface) {
            throw ContainerDependencyException::unexpectedType($interface, $interface, $resolved);
        }

        /** @var T $resolved */
        return $resolved;
    }

    /**
     * Optional resolve: missing registration → null.
     * Resolution / type failures still throw (does not hide broken bindings).
     *
     * @template T of object
     * @param class-string<T> $interface
     * @return T|null
     */
    public static function getOrNull(ContainerInterface $container, string $interface): ?object
    {
        if (!$container->has($interface)) {
            return null;
        }

        return self::get($container, $interface);
    }
}
