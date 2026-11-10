<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Dumper\HeadersFileDumper;
use Bakslash\StaticSiteBundle\Event\GenerationFinishedEvent;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

final class HeadersFileDumperTest extends TestCase
{
    use ExpectsExceptionMessage;

    private const string BEGIN = '# Written by static-site:generate. Edit outside these lines.';
    private const string END = '# End of static-site:generate.';
    private const array CSP = ['content-security-policy' => ["default-src 'self'"]];

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
        $this->generate($this->dumper(), [
            '/' => self::CSP,
            '/blog' => self::CSP + ['x-robots-tag' => ['noindex']],
            '/about' => self::CSP,
        ]);

        $this->assertSame($this->block([
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            '/blog',
            '  X-Robots-Tag: noindex',
        ]), $this->file());
    }

    public function testAHeaderWhoseValueDiffersStaysWithEachPage(): void
    {
        $this->generate($this->dumper(), [
            '/' => ['x-frame-options' => ['DENY']],
            '/embed' => ['x-frame-options' => ['SAMEORIGIN']],
        ]);

        $this->assertSame($this->block([
            '/',
            '  X-Frame-Options: DENY',
            '/embed',
            '  X-Frame-Options: SAMEORIGIN',
        ]), $this->file());
    }

    public function testWritesOnlyTheHeadersThatDescribeThePage(): void
    {
        $this->generate($this->dumper(), ['/' => self::CSP + [
            'set-cookie' => ['session=abc'],
            'cache-control' => ['no-cache, private'],
            'link' => ['</app.css>; rel=preload', '</app.js>; rel=preload'],
        ]]);

        $this->assertSame($this->block([
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            '  Link: </app.css>; rel=preload, </app.js>; rel=preload',
        ]), $this->file());
    }

    public function testAPageWithoutHeadersIsLeftOutOfTheSharedRule(): void
    {
        $this->generate($this->dumper(), ['/' => self::CSP, '/a' => self::CSP, '/old' => []]);

        $this->assertSame($this->block([
            '/*',
            "  Content-Security-Policy: default-src 'self'",
        ]), $this->file());
    }

    public function testWritesTheRuleForTheUrlTheHostServes(): void
    {
        $dumper = $this->dumper(cleanUrls: true);
        $dumper->dump('/blog', new RenderedPage('', 'html', ['x-robots-tag' => ['noindex']]));
        $dumper->dump('/feed', new RenderedPage('', 'xml', ['x-robots-tag' => ['none']]));
        $dumper->dump('/page.html', new RenderedPage('', 'html', ['x-robots-tag' => ['nofollow']]));
        $dumper->onGenerationFinished(new GenerationFinishedEvent([]));

        $this->assertSame($this->block([
            '/blog/',
            '  X-Robots-Tag: noindex',
            '/feed.xml',
            '  X-Robots-Tag: none',
            '/page',
            '  X-Robots-Tag: nofollow',
        ]), $this->file());
    }

    public function testAFullRunReplacesTheRulesOfThePreviousOne(): void
    {
        $this->generate($this->dumper(), [
            '/' => ['content-security-policy' => ['v1']],
            '/legacy' => ['content-security-policy' => ['v1']],
        ]);
        $this->generate($this->dumper(), [
            '/' => ['content-security-policy' => ['v2']],
            '/a' => ['content-security-policy' => ['v2']],
        ]);

        $this->assertSame($this->block(['/*', '  Content-Security-Policy: v2']), $this->file());
    }

    public function testAPartialRunWritesNothing(): void
    {
        $this->generate($this->dumper(), ['/' => self::CSP]);
        $before = $this->file();

        $dumper = $this->dumper();
        $dumper->dump('/', new RenderedPage('', 'html', ['content-security-policy' => ['other']]));
        $dumper->onGenerationFinished(new GenerationFinishedEvent(['/'], partial: true));

        $this->assertSame($before, $this->file());
    }

    public function testKeepsTheLinesWrittenByHand(): void
    {
        (new LocalFilesystem())->dumpFile($this->dir.'/_headers', implode("\n", [
            '/assets/*',
            '  Cache-Control: max-age=31536000',
            self::BEGIN,
            '/stale',
            '  X-Frame-Options: DENY',
            self::END,
            '/admin',
            '  X-Robots-Tag: noindex',
        ])."\n");

        $this->generate($this->dumper(), ['/' => self::CSP]);

        $this->assertSame(implode("\n", [
            '/assets/*',
            '  Cache-Control: max-age=31536000',
            self::BEGIN,
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            self::END,
            '/admin',
            '  X-Robots-Tag: noindex',
        ])."\n", $this->file());
    }

    public function testThrowsWhenTheEndOfTheGeneratedRulesIsMissing(): void
    {
        (new LocalFilesystem())->dumpFile($this->dir.'/_headers', self::BEGIN."\n/old\n");

        $this->expectException(DumpingException::class);
        $this->expectExceptionMessageToBe(\sprintf('Cannot write the headers file "_headers": it has the "%s" line but not the "%s" one.', self::BEGIN, self::END));

        $this->generate($this->dumper(), ['/' => self::CSP]);
    }

    public function testDoesNotRecordAPageTheInnerDumperFailedToWrite(): void
    {
        $inner = $this->createStub(DumperInterface::class);
        $inner->method('dump')->willThrowException(new DumpingException('boom'));
        $dumper = $this->dumper(inner: $inner);

        try {
            $dumper->dump('/', new RenderedPage('', 'html', self::CSP));
        } catch (DumpingException) {
        }
        $dumper->onGenerationFinished(new GenerationFinishedEvent([]));

        $this->assertFileDoesNotExist($this->dir.'/_headers');
    }

    public function testWarnsPastTheRuleLimitOfCloudflarePages(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'The headers file "{file}" holds {count} rules, while Cloudflare Pages ignores those past the {max}th.',
            ['file' => '_headers', 'count' => 101, 'max' => 100],
        );

        $pages = [];
        for ($i = 0; $i < 101; ++$i) {
            $pages['/'.$i] = ['link' => [\sprintf('</%d.css>; rel=preload', $i)]];
        }

        $this->generate($this->dumper(logger: $logger), $pages);
    }

    public function testCountsTheHandWrittenRulesTowardsTheLimit(): void
    {
        (new LocalFilesystem())->dumpFile($this->dir.'/_headers', implode("\n", array_map(static fn (int $i): string => \sprintf("/assets/%d/*\n  Cache-Control: max-age=60", $i), range(1, 100)))."\n");

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->anything(), ['file' => '_headers', 'count' => 101, 'max' => 100]);

        $this->generate($this->dumper(logger: $logger), ['/' => self::CSP]);
    }

    public function testSkipsTheNullValuesOfAHeader(): void
    {
        $this->generate($this->dumper(), ['/' => ['link' => ['</a.css>; rel=preload', null]]]);

        $this->assertSame($this->block(['/*', '  Link: </a.css>; rel=preload']), $this->file());
    }

    public function testWritesNothingWhenNoPageWasWritten(): void
    {
        $this->dumper()->onGenerationFinished(new GenerationFinishedEvent([]));

        $this->assertFileDoesNotExist($this->dir.'/_headers');
    }

    private function dumper(bool $cleanUrls = false, ?DumperInterface $inner = null, ?LoggerInterface $logger = null): HeadersFileDumper
    {
        return new HeadersFileDumper(
            $inner ?? $this->createStub(DumperInterface::class),
            new Filesystem(new LocalFilesystemAdapter($this->dir)),
            '_headers',
            $cleanUrls,
            $logger,
        );
    }

    /**
     * @param array<string, array<string, list<string|null>>> $pages
     */
    private function generate(HeadersFileDumper $dumper, array $pages): void
    {
        foreach ($pages as $uri => $headers) {
            $dumper->dump($uri, new RenderedPage('', 'html', $headers));
        }

        $dumper->onGenerationFinished(new GenerationFinishedEvent(array_keys($pages)));
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
