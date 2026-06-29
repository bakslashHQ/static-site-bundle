<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\StaticPagesGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class StaticPagesGeneratorTest extends TestCase
{
    public function testGenerateContent(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('foo-content'));

        $generator = new StaticPagesGenerator($kernel, 'http://localhost', '');
        ['content' => $content, 'format' => $format] = $generator->generate('/whatever');

        $this->assertSame('foo-content', $content);
        $this->assertSame('html', $format);
    }

    public function testGenerateUsesRouteFormatOverContentType(): void
    {
        // Simulates Symfony's router setting the route format on the request,
        // independently of the response's Content-Type (e.g. atom+xml -> "xml" route format).
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(static function (Request $request): Response {
            $request->setRequestFormat('xml');

            return new Response('<feed/>', headers: [
                'Content-Type' => 'application/atom+xml',
            ]);
        });

        $generator = new StaticPagesGenerator($kernel, 'http://localhost', '');
        ['format' => $format] = $generator->generate('/blog/feed.xml');

        $this->assertSame('xml', $format);
    }

    public function testThrowOnNotOk(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('not-found', Response::HTTP_NOT_FOUND));

        $generator = new StaticPagesGenerator($kernel, 'http://localhost', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected URI "/whatever" to return status code 200, got 404.');
        $generator->generate('/whatever');
    }

    public function testThrowOnException(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willThrowException(new \RuntimeException('Kernel error'));

        $generator = new StaticPagesGenerator($kernel, 'http://localhost', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot generate page for URI "/whatever".');
        $generator->generate('/whatever');
    }
}
