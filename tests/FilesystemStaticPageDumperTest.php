<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\FilesystemStaticPageDumper;
use PHPUnit\Framework\TestCase;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemStaticPageDumperTest extends TestCase
{
    private string $outputDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = \sprintf('%s/bakslash_ssg_test/', sys_get_temp_dir());
        $this->filesystem = new Filesystem();

        if ($this->filesystem->exists($this->outputDir)) {
            $this->filesystem->remove($this->outputDir);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->filesystem->exists($this->outputDir)) {
            $this->filesystem->remove($this->outputDir);
        }
    }

    public function testDumpContent(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->never())->method('minify');

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/page-foo', 'dummy-content');

        $expectedPath = \sprintf('%s/page-foo', $this->outputDir);

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('dummy-content', $this->filesystem->readFile($expectedPath));
    }

    public function testDumpRootAsIndexHtml(): void
    {
        $minify = $this->createStub(MinifierInterface::class);
        $minify->method('minify')->willReturnArgument(0);

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/', '<html></html>');

        $expectedPath = \sprintf('%s/index.html', $this->outputDir);

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('<html></html>', $this->filesystem->readFile($expectedPath));
    }

    public function testAppendFormat(): void
    {
        $minify = $this->createStub(MinifierInterface::class);
        $minify->method('minify')->willReturnArgument(0);

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/page-foo', 'dummy-content', 'html');

        $expectedPath = \sprintf('%s/page-foo.html', $this->outputDir);

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('dummy-content', $this->filesystem->readFile($expectedPath));
    }

    public function testMinifyHtml(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->once())->method('minify')->with('<html></html>', 'html')->willReturnArgument(0);

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/page', '<html></html>', 'html');
    }

    public function testMinifyXml(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->once())->method('minify')->with('<xml/>', 'xml')->willReturnArgument(0);

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/sitemap.xml', '<xml/>', 'xml');
    }

    public function testDoNotMinifyOtherFormats(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->never())->method('minify');

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/data.json', '{}', 'json');
    }

    public function testDoNotAppendFormatIfAlreadyPresent(): void
    {
        $minify = $this->createStub(MinifierInterface::class);
        $minify->method('minify')->willReturnArgument(0);

        $dumper = new FilesystemStaticPageDumper($minify, $this->outputDir);
        $dumper->dump('/sitemap.xml', '<xml/>', 'xml');

        $expectedPath = \sprintf('%s/sitemap.xml', $this->outputDir);

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('<xml/>', $this->filesystem->readFile($expectedPath));
    }
}
