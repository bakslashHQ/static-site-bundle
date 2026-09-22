<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Renderer\PageRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ResetInterface;

final class PageRendererTest extends TestCase
{
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

    public function testDerivesBasePathFromBaseUri(): void
    {
        $captured = null;
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(static function (Request $request) use (&$captured): Response {
            $captured = $request;

            return new Response('ok');
        });

        $this->renderer($kernel, 'https://example.com/my-app/')->render('/foo');

        $this->assertInstanceOf(Request::class, $captured);
        $this->assertSame('https://example.com/my-app/foo', $captured->getUri());
        $this->assertSame('/my-app', $captured->getBasePath());
        $this->assertSame('/foo', $captured->getPathInfo());
    }

    #[DataProvider('scriptNameProvider')]
    public function testSetsScriptServerVarsFromBaseUri(string $baseUri, string $expectedScriptName): void
    {
        $captured = null;
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(static function (Request $request) use (&$captured): Response {
            $captured = $request;

            return new Response('ok');
        });

        $this->renderer($kernel, $baseUri)->render('/foo');

        $this->assertInstanceOf(Request::class, $captured);
        $this->assertSame($expectedScriptName, $captured->server->get('SCRIPT_NAME'));
        $this->assertSame($expectedScriptName, $captured->server->get('SCRIPT_FILENAME'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scriptNameProvider(): iterable
    {
        yield 'sub-path' => ['https://example.com/my-app', '/my-app/index.php'];
        yield 'sub-path with trailing slash' => ['https://example.com/my-app/', '/my-app/index.php'];
        yield 'no path' => ['http://localhost', '/index.php'];
        yield 'empty base uri' => ['', '/index.php'];
    }

    public function testThrowOnNotOk(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('not-found', Response::HTTP_NOT_FOUND));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessageIs('Expected URI "/whatever" to return HTTP 200 or a redirect, got 404.');
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
        $this->expectExceptionMessageIs('Cannot render page for URI "/whatever": Kernel error');
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
        $this->expectExceptionMessageIs('URI "/whatever" returned a response whose content cannot be captured (streamed and binary responses are not supported).');
        $this->renderer($kernel)->render('/whatever');
    }

    public function testHandlesTheRequestWithoutCatchingExceptions(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(Request::class), HttpKernelInterface::MAIN_REQUEST, false)
            ->willReturn(new Response('ok'));

        (new PageRenderer($kernel, $this->createStub(ResetInterface::class), new RequestContext(), 'http://localhost'))->render('/foo');
    }

    public function testResetsServicesAfterSuccess(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('ok'));

        $resetter = $this->createMock(ResetInterface::class);
        $resetter->expects($this->once())->method('reset');

        (new PageRenderer($kernel, $resetter, new RequestContext(), 'http://localhost'))->render('/foo');
    }

    public function testResetsServicesAfterFailure(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('boom', Response::HTTP_INTERNAL_SERVER_ERROR));

        $resetter = $this->createMock(ResetInterface::class);
        $resetter->expects($this->once())->method('reset');

        try {
            (new PageRenderer($kernel, $resetter, new RequestContext(), 'http://localhost'))->render('/whatever');
        } catch (RenderingException) {
        }
    }

    #[DataProvider('contextProvider')]
    public function testFallsBackToTheRouterContextWhenBaseUriIsEmpty(RequestContext $context, string $expectedUri): void
    {
        $captured = null;
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(static function (Request $request) use (&$captured): Response {
            $captured = $request;

            return new Response('ok');
        });

        (new PageRenderer($kernel, $this->createStub(ResetInterface::class), $context, ''))->render('/foo');

        $this->assertInstanceOf(Request::class, $captured);
        $this->assertSame($expectedUri, $captured->getUri());
    }

    /**
     * @return iterable<string, array{RequestContext, string}>
     */
    public static function contextProvider(): iterable
    {
        yield 'scheme, host and base path' => [new RequestContext('/my-app', 'GET', 'example.com', 'https'), 'https://example.com/my-app/foo'];
        yield 'non-standard port' => [new RequestContext('', 'GET', 'example.com', 'http', 8080), 'http://example.com:8080/foo'];
    }

    private function renderer(HttpKernelInterface $kernel, string $baseUri = 'http://localhost'): PageRenderer
    {
        return new PageRenderer($kernel, $this->createStub(ResetInterface::class), new RequestContext(), $baseUri);
    }
}
