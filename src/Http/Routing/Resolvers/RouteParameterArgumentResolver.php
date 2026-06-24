<?php declare(strict_types=1);

namespace Concept\Core\Http\Routing\Resolvers;

use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

final class RouteParameterArgumentResolver implements ArgumentResolverInterface
{
    public function supports(ReflectionParameter $parameter, array $vars): bool
    {
        return array_key_exists($parameter->getName(), $vars);
    }

    public function resolve(ReflectionParameter $parameter, ServerRequestInterface $request, array $vars): mixed
    {
        return $vars[$parameter->getName()];
    }
}
