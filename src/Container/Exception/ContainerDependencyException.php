<?php declare(strict_types=1);

namespace Concept\Core\Container\Exception;

use RuntimeException;
use Throwable;

final class ContainerDependencyException extends RuntimeException
{
    private const string ERR_NOT_REGISTERED = 'Container service (%s) is not registered.';
    private const string ERR_UNEXPECTED_TYPE = 'Container service (%s) must be an instance of %s, %s given.';

    public static function notRegistered(string $interface, ?Throwable $previous = null): self
    {
        return new self(sprintf(self::ERR_NOT_REGISTERED, $interface), 0, $previous);
    }

    public static function unexpectedType(string $interface, string $expected, mixed $resolved): self
    {
        $actual = is_object($resolved) ? $resolved::class : get_debug_type($resolved);

        return new self(sprintf(self::ERR_UNEXPECTED_TYPE, $interface, $expected, $actual));
    }
}
