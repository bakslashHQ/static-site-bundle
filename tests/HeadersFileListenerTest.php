<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Event\GenerationFinishedEvent;
use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\EventListener\HeadersFileListener;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\Routing\RequestContext;

final class HeadersFileListenerTest extends TestCase
{
    private const BEGIN = '# Written by static-site:generate. Edit outside these lines.';
    private const END = '# End of static-site:generate.';
    private const CSP = ['content-security-policy' => ["default-src 'self'"]];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/bakslash_static_site_headers_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new LocalFilesystem())->remove($this->dir);
    }

    public function testSharedHeadersGoToOneRuleAndEachPageKeepsOnlyWhatDiffers(): void
    {
        $this->generate($this->listener(), [
            '/' => self::CSP + ['x-robots-tag' => ['noindex']],
            '/about' => self::CSP,
            '/blog' => self::CSP + ['x-frame-options' => ['DENY']],
        ]);

        $this->assertSame($this->block([
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            '/',
            '  X-Robots-Tag: noindex',
            '# /about (the /* rule only)',
            '/blog',
            '  X-Frame-Options: DENY',
        ]), $this->file());
    }

    public function testAHeaderWhoseValueDiffersStaysWithEachPage(): void
    {
        $this->generate($this->listener(), [
            '/a' => ['content-security-policy' => ["default-src 'self'"]],
            '/b' => ['content-security-policy' => ["default-src 'none'"]],
        ]);

        $this->assertSame($this->block([
            '/a',
            "  Content-Security-Policy: default-src 'self'",
            '/b',
            "  Content-Security-Policy: default-src 'none'",
        ]), $this->file());
    }

    public function testWritesOnlyTheHeadersThatDescribeThePage(): void
    {
        $this->generate($this->listener(), ['/' => self::CSP + [
            'set-cookie' => ['session=abc'],
            'cache-control' => ['no-cache, private'],
            'vary' => ['Accept-Language'],
            'date' => ['Tue, 20 Oct 2026 10:00:00 GMT'],
            'link' => ['</a.css>; rel=preload', '</b.js>; rel=preload'],
        ]]);

        $this->assertSame($this->block([
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            '  Link: </a.css>; rel=preload, </b.js>; rel=preload',
            '# / (the /* rule only)',
        ]), $this->file());
    }

    #[DataProvider('servedPathProvider')]
    public function testWritesTheRuleForThePathTheHostServes(string $uri, string $format, bool $cleanUrls, string $baseUri, string $path): void
    {
        // A second page with nothing in common, so that the first keeps its own rule.
        $this->generate($this->listener($cleanUrls, $baseUri), [$uri => self::CSP, '/other' => []], $format);

        $this->assertStringContainsString("\n".$path."\n  Content-Security-Policy:", $this->file());
    }

    /**
     * @return iterable<string, array{string, string, bool, string, string}>
     */
    public static function servedPathProvider(): iterable
    {
        yield 'html page' => ['/blog', 'html', false, 'https://example.com', '/blog'];
        yield 'directory index' => ['/blog/', 'html', false, 'https://example.com', '/blog/'];
        yield 'clean url' => ['/blog', 'html', true, 'https://example.com', '/blog/'];
        yield 'other format' => ['/feed', 'xml', false, 'https://example.com', '/feed.xml'];
        yield 'path with its extension' => ['/feed.xml', 'xml', false, 'https://example.com', '/feed.xml'];
        yield 'base path' => ['/blog', 'html', false, 'https://example.com/docs', '/docs/blog'];
    }

    public function testAPartialRunUpdatesItsPagesAmongTheOthersAndKeepsWhatIsWrittenByHand(): void
    {
        $this->generate($this->listener(), [
            '/' => self::CSP,
            '/about' => self::CSP + ['x-frame-options' => ['DENY']],
        ]);
        (new LocalFilesystem())->dumpFile($this->dir.'/_headers', "# Mine\n/assets/*\n  Cache-Control: max-age=31536000\n".$this->file());

        // A run filtered to /about: / stays as it was, and so do the lines written by hand.
        $this->generate($this->listener(), ['/about' => self::CSP + ['x-frame-options' => ['SAMEORIGIN']]]);

        $this->assertSame("# Mine\n/assets/*\n  Cache-Control: max-age=31536000\n".$this->block([
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            '# / (the /* rule only)',
            '/about',
            '  X-Frame-Options: SAMEORIGIN',
        ]), $this->file());
    }

    public function testAPageOnlyUnderTheSharedRuleIsNotForgottenByAPartialRun(): void
    {
        $this->generate($this->listener(), ['/' => self::CSP, '/about' => self::CSP]);

        // /about now sends something else: "/" keeps its CSP, which is no longer shared.
        $this->generate($this->listener(), ['/about' => ['x-frame-options' => ['DENY']]]);

        $this->assertSame($this->block([
            '/',
            "  Content-Security-Policy: default-src 'self'",
            '/about',
            '  X-Frame-Options: DENY',
        ]), $this->file());
    }

    public function testDropsAValueThatWouldForgeAnotherRule(): void
    {
        $this->generate($this->listener(), ['/blog' => [
            'x-frame-options' => ['DENY'],
            'x-robots-tag' => ["noindex\n/admin\n  Access-Control-Allow-Origin: *"],
        ]]);

        $this->assertStringNotContainsString('Access-Control-Allow-Origin', $this->file());
        $this->assertStringContainsString('X-Frame-Options: DENY', $this->file());
    }

    public function testWritesNothingWhenNoPageWasWritten(): void
    {
        $listener = $this->listener();
        $listener->onPageRendered(new PageRenderedEvent('/broken', new RenderedPage('', 'html', self::CSP)));
        $listener->onGenerationFinished(new GenerationFinishedEvent([]));

        $this->assertFileDoesNotExist($this->dir.'/_headers');
    }

    private function listener(bool $cleanUrls = false, string $baseUri = 'https://example.com'): HeadersFileListener
    {
        return new HeadersFileListener(
            new Filesystem(new LocalFilesystemAdapter($this->dir)),
            new SiteContext(new RequestContext(), $baseUri),
            '_headers',
            $cleanUrls,
        );
    }

    /**
     * @param array<string, array<string, list<string>>> $pages
     */
    private function generate(HeadersFileListener $listener, array $pages, string $format = 'html'): void
    {
        foreach ($pages as $uri => $headers) {
            $listener->onPageRendered(new PageRenderedEvent($uri, new RenderedPage('', $format, $headers)));
        }

        $listener->onGenerationFinished(new GenerationFinishedEvent(array_keys($pages)));
    }

    /**
     * @param list<string> $lines
     */
    private function block(array $lines): string
    {
        return implode("\n", [self::BEGIN, ...$lines, self::END])."\n";
    }

    private function file(): string
    {
        return (string) file_get_contents($this->dir.'/_headers');
    }
}
