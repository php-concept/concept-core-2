<?php declare(strict_types=1);

namespace Tests\Unit\Http\Request;

use Concept\Core\Http\Request\RequestContext;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    public function testCurrentIsNullBeforeSet(): void
    {
        $context = new RequestContext();

        $this->assertNull($context->current());
    }

    public function testSetStoresRequestAndCurrentReturnsIt(): void
    {
        $context = new RequestContext();
        $request = new ServerRequest();

        $context->set($request);

        $this->assertSame($request, $context->current());
    }

    public function testResetClearsStoredRequest(): void
    {
        $context = new RequestContext();
        $context->set(new ServerRequest());

        $context->reset();

        $this->assertNull($context->current());
    }
}
