<?php declare(strict_types=1);

namespace Concept\Core\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

interface ArgumentResolverInterface
{
    public function supports(ReflectionParameter $parameter): bool;

    /**
     * @param ReflectionParameter $parameter
     * @param ServerRequestInterface $request
     * @param array<string> $vars
     * @return mixed
     */
    public function resolve(ReflectionParameter $parameter, ServerRequestInterface $request, array $vars): mixed;
}