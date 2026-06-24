<?php declare(strict_types=1);

namespace Concept\Core\Http\Routing\Resolvers;

use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionNamedType;
use ReflectionParameter;

final class ServerRequestArgumentResolver implements ArgumentResolverInterface
{
    public function supports(ReflectionParameter $parameter, array $vars): bool
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $typeName = $type->getName();

        return $typeName === ServerRequestInterface::class
            || is_subclass_of($typeName, ServerRequestInterface::class);
    }

    public function resolve(ReflectionParameter $parameter, ServerRequestInterface $request, array $vars): mixed
    {
        return $request;
    }
}
