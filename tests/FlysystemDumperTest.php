<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Dumper\FlysystemDumper;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;

final class FlysystemDumperTest extends TestCase
{
    public function testWritesToTheResolvedPath(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('foo.html', 'content');

        (new FlysystemDumper($filesystem))->dump('/foo', 'content', 'html');
    }

    public function testWritesRootAsIndexHtml(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('index.html', '<html></html>');

        (new FlysystemDumper($filesystem))->dump('/', '<html></html>');
    }

    public function testAppendsTheFormatExtension(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('sitemap.xml', '<urlset/>');

        (new FlysystemDumper($filesystem))->dump('/sitemap', '<urlset/>', 'xml');
    }

    public function testDoesNotAppendTheFormatWhenAlreadyPresent(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('feed.xml', '<feed/>');

        (new FlysystemDumper($filesystem))->dump('/feed.xml', '<feed/>', 'xml');
    }

    public function testWritesWithoutAFormat(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with('page', 'content');

        (new FlysystemDumper($filesystem))->dump('/page', 'content');
    }

    public function testThrowsDumpingExceptionWhenWriteFails(): void
    {
        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('write')->willThrowException(UnableToWriteFile::atLocation('foo.html'));

        $dumper = new FlysystemDumper($filesystem);

        $this->expectException(DumpingException::class);
        $this->expectExceptionMessageIs('Cannot dump page for URI "/foo".');
        $dumper->dump('/foo', 'content');
    }

    public function testPurgeDeletesEveryTopLevelEntry(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('foo.html'),
            new DirectoryAttributes('articles'),
        ]));
        $filesystem->expects($this->once())->method('delete')->with('foo.html');
        $filesystem->expects($this->once())->method('deleteDirectory')->with('articles');

        (new FlysystemDumper($filesystem))->purge();
    }

    public function testThrowsDumpingExceptionWhenPurgeFails(): void
    {
        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([new FileAttributes('foo.html')]));
        $filesystem->method('delete')->willThrowException(UnableToDeleteFile::atLocation('foo.html'));

        $dumper = new FlysystemDumper($filesystem);

        $this->expectException(DumpingException::class);
        $this->expectExceptionMessageIs('Cannot purge the previously generated pages.');
        $dumper->purge();
    }
}
