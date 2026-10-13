<?php declare(strict_types=1);

namespace Concept\Core\Container\Exception;

use RuntimeException;
use Throwable;

final class ContainerDependencyException extends RuntimeException
{
    private const string ERR_NOT_REGISTERED = 'Container service (%s) is not registered.';
    private const string ERR_UNEXPECTED_TYPE = 'Container service (%s) must be an instance of %s, %s given.';
    private const string ERR_RESOLUTION_FAILED = 'Container service (%s) could not be resolved.';

    private const int CODE_NOT_REGISTERED = 1;

    private const int CODE_UNEXPECTED_TYPE = 2;

    private const int CODE_RESOLUTION_FAILED = 3;

    public static function notRegistered(string $interface, ?Throwable $previous = null): self
    {
        return new self(sprintf(self::ERR_NOT_REGISTERED, $interface), self::CODE_NOT_REGISTERED, $previous);
    }

    public static function unexpectedType(string $interface, string $expected, mixed $resolved): self
    {
        $actual = is_object($resolved) ? $resolved::class : get_debug_type($resolved);

        return new self(sprintf(self::ERR_UNEXPECTED_TYPE, $interface, $expected, $actual), self::CODE_UNEXPECTED_TYPE);
    }

    public static function resolutionFailed(string $interface, Throwable $previous): self
    {
        return new self(sprintf(self::ERR_RESOLUTION_FAILED, $interface), self::CODE_RESOLUTION_FAILED, $previous);
    }

    public function isNotRegistered(): bool
    {
        return $this->getCode() === self::CODE_NOT_REGISTERED;
    }

    public function isResolutionFailed(): bool
    {
        return $this->getCode() === self::CODE_RESOLUTION_FAILED;
    }
}
