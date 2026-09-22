<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Dumper\FlysystemDumper;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;

final class FlysystemDumperTest extends TestCase
{
    use ExpectsExceptionMessage;

    public function testWritesToTheResolvedPath(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('foo.html', 'content');

        (new FlysystemDumper($filesystem))->dump('/foo', new RenderedPage('content', 'html'));
    }

    public function testWritesRootAsIndexHtml(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('index.html', '<html></html>');

        (new FlysystemDumper($filesystem))->dump('/', new RenderedPage('<html></html>', 'html'));
    }

    public function testWritesRootWithTheFormatExtension(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('index.xml', '<feed/>');

        (new FlysystemDumper($filesystem))->dump('/', new RenderedPage('<feed/>', 'xml'));
    }

    public function testWritesATrailingSlashAsADirectoryIndex(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('blog/index.html', 'content');

        (new FlysystemDumper($filesystem))->dump('/blog/', new RenderedPage('content', 'html'));
    }

    public function testAppendsTheFormatExtension(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('sitemap.xml', '<urlset/>');

        (new FlysystemDumper($filesystem))->dump('/sitemap', new RenderedPage('<urlset/>', 'xml'));
    }

    public function testDoesNotAppendTheFormatWhenAlreadyPresent(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('feed.xml', '<feed/>');

        (new FlysystemDumper($filesystem))->dump('/feed.xml', new RenderedPage('<feed/>', 'xml'));
    }

    public function testThrowsDumpingExceptionWhenWriteFails(): void
    {
        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('write')->willThrowException(UnableToWriteFile::atLocation('foo.html'));

        $dumper = new FlysystemDumper($filesystem);

        $this->expectException(DumpingException::class);
        $this->expectExceptionMessageToBe('Cannot dump page for URI "/foo".');
        $dumper->dump('/foo', new RenderedPage('content', 'html'));
    }

    public function testWritesCleanUrlAsADirectoryIndex(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('articles/bar/index.html', 'content');

        (new FlysystemDumper($filesystem, cleanUrls: true))->dump('/articles/bar', new RenderedPage('content', 'html'));
    }

    public function testWritesRootAsIndexHtmlWithCleanUrls(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('index.html', '<html></html>');

        (new FlysystemDumper($filesystem, cleanUrls: true))->dump('/', new RenderedPage('<html></html>', 'html'));
    }

    public function testKeepsExplicitHtmlFilesWithCleanUrls(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('foo.html', 'content');

        (new FlysystemDumper($filesystem, cleanUrls: true))->dump('/foo.html', new RenderedPage('content', 'html'));
    }

    public function testKeepsNonHtmlFilesWithCleanUrls(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('feed.xml', '<feed/>');

        (new FlysystemDumper($filesystem, cleanUrls: true))->dump('/feed', new RenderedPage('<feed/>', 'xml'));
    }
}
