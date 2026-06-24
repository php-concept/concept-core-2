<?php declare(strict_types=1);

namespace Concept\Core\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface RequestContextInterface
{
    public function set(ServerRequestInterface $request): void;

    public function current(): ?ServerRequestInterface;

    public function reset(): void;
}
