<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Renderer\PageRenderer;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ResetInterface;

final class PageRendererTest extends TestCase
{
    use ExpectsExceptionMessage;

    public function testRenderContent(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('foo-content'));

        $page = $this->renderer($kernel)->render('/whatever');

        $this->assertSame('foo-content', $page->content);
        $this->assertSame('html', $page->format);
    }

    public function testDerivesFormatFromResponseContentType(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('{}', headers: [
            'Content-Type' => 'application/json',
        ]));

        $page = $this->renderer($kernel)->render('/api/data');

        $this->assertSame('json', $page->format);
    }

    public function testPrefersTheExplicitRequestFormatOverContentType(): void
    {
        // The route declares "xml" (e.g. a ".xml" feed), so that wins over the response's
        // application/atom+xml Content-Type.
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(static function (Request $request): Response {
            $request->setRequestFormat('xml');

            return new Response('<feed/>', headers: [
                'Content-Type' => 'application/atom+xml',
            ]);
        });

        $page = $this->renderer($kernel)->render('/blog/feed.xml');

        $this->assertSame('xml', $page->format);
    }

    public function testThrowOnNotOk(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('not-found', Response::HTTP_NOT_FOUND));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessageToBe('Expected URI "/whatever" to return HTTP 200 or a redirect, got 404.');
        $this->renderer($kernel)->render('/whatever');
    }

    public function testRedirectProducesAMetaRefreshStub(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('', Response::HTTP_FOUND, ['Location' => '/new']));

        $page = $this->renderer($kernel)->render('/old');

        $this->assertSame('html', $page->format);
        $this->assertStringContainsString('http-equiv="refresh"', $page->content);
        $this->assertStringContainsString('0; url=/new', $page->content);
    }

    public function testThrowOnException(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willThrowException(new \RuntimeException('Kernel error'));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessageToBe('Cannot render page for URI "/whatever": Kernel error');
        $this->renderer($kernel)->render('/whatever');
    }

    public function testPreservesTheUnderlyingExceptionAsCause(): void
    {
        $cause = new \RuntimeException('Kernel error');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willThrowException($cause);

        try {
            $this->renderer($kernel)->render('/whatever');
            $this->fail('Expected a RenderingException.');
        } catch (RenderingException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    public function testThrowsWhenResponseContentCannotBeCaptured(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new StreamedResponse(static fn () => print ('streamed')));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessageToBe('URI "/whatever" returned a response whose content cannot be captured (streamed and binary responses are not supported).');
        $this->renderer($kernel)->render('/whatever');
    }

    public function testHandlesTheRequestWithoutCatchingExceptions(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(Request::class), HttpKernelInterface::MAIN_REQUEST, false)
            ->willReturn(new Response('ok'));

        $this->renderer($kernel)->render('/foo');
    }

    public function testResetsServicesAfterSuccess(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('ok'));

        $resetter = $this->createMock(ResetInterface::class);
        $resetter->expects($this->once())->method('reset');

        $this->renderer($kernel, $resetter)->render('/foo');
    }

    public function testResetsServicesAfterFailure(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('boom', Response::HTTP_INTERNAL_SERVER_ERROR));

        $resetter = $this->createMock(ResetInterface::class);
        $resetter->expects($this->once())->method('reset');

        try {
            $this->renderer($kernel, $resetter)->render('/whatever');
        } catch (RenderingException) {
        }
    }

    private function renderer(HttpKernelInterface $kernel, ?ResetInterface $servicesResetter = null): PageRenderer
    {
        return new PageRenderer(
            $kernel,
            $servicesResetter ?? $this->createStub(ResetInterface::class),
            new SiteContext(new RequestContext(), 'http://localhost'),
        );
    }
}
