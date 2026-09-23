<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Renderer\PageRenderer;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;

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

    #[DataProvider('unsupportedRedirectTargetProvider')]
    public function testThrowOnARedirectTargetAStaticPageCannotLinkTo(string $location): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('', Response::HTTP_FOUND, ['Location' => $location]));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessageToBe(\sprintf('URI "/old" redirects to "%s"; a static page can only redirect to an http(s) or site-relative target.', $location));
        $this->renderer($kernel)->render('/old');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedRedirectTargetProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(document.domain)'];
        yield 'obfuscated javascript' => ["java\tscript:alert(1)"];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'mailto' => ['mailto:someone@example.com'];
    }

    public function testKeepsARedirectTargetThatIsNotValidUtf8(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('', Response::HTTP_FOUND, [
            'Location' => "/caf\xE9/page",
        ]));

        $page = $this->renderer($kernel)->render('/old');

        $this->assertStringNotContainsString('content="0; url="', $page->content);
        $this->assertStringContainsString('/caf', $page->content);
    }

    public function testThrowOnRedirectWithoutALocation(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('', Response::HTTP_FOUND));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessageToBe('URI "/old" returned a 302 redirect without a "Location" header.');
        $this->renderer($kernel)->render('/old');
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

    private function renderer(HttpKernelInterface $kernel): PageRenderer
    {
        return new PageRenderer($kernel, new SiteContext(new RequestContext(), 'http://localhost'));
    }
}
