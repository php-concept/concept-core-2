<?php declare(strict_types=1);

namespace Concept\Core\Http\Routing\Exception;

use ReflectionParameter;
use RuntimeException;

final class UnresolvedArgumentException extends RuntimeException
{
    private const string ERR_UNRESOLVED = 'Cannot resolve parameter $%s of type %s: no ArgumentResolver supported it.';

    public static function forParameter(ReflectionParameter $parameter): self
    {
        $type = $parameter->getType();

        return new self(sprintf(
            self::ERR_UNRESOLVED,
            $parameter->getName(),
            $type !== null ? (string) $type : 'mixed',
        ));
    }
}
