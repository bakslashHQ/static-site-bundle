<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Dumper\PagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PagePathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'root' => ['/', 'html', false, 'index.html', '/'];
        yield 'page' => ['/blog', 'html', false, 'blog.html', '/blog'];
        yield 'trailing slash' => ['/blog/', 'html', false, 'blog/index.html', '/blog/'];
        yield 'clean URL' => ['/blog', 'html', true, 'blog/index.html', '/blog/'];
        yield 'explicit extension' => ['/page.html', 'html', false, 'page.html', '/page'];
        yield 'other format' => ['/feed', 'xml', true, 'feed.xml', '/feed.xml'];
        yield 'other format index' => ['/', 'xml', false, 'index.xml', '/index.xml'];
    }

    #[DataProvider('pathProvider')]
    public function testResolvesTheWrittenAndServedPaths(string $uri, string $format, bool $cleanUrls, string $written, string $served): void
    {
        $this->assertSame($written, PagePath::written($uri, $format, $cleanUrls));
        $this->assertSame($served, PagePath::served($written));
    }
}
