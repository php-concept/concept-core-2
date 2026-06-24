<?php declare(strict_types=1);

namespace Concept\Core\Http\Request;

use Concept\Core\Http\Contracts\RequestContextInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequestContext implements RequestContextInterface
{
    private ?ServerRequestInterface $request = null;

    public function set(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function current(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function reset(): void
    {
        $this->request = null;
    }
}
