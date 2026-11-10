<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Dumper;

use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

/**
 * @internal
 */
final readonly class FlysystemDumper implements DumperInterface
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private bool $cleanUrls = false,
    ) {
    }

    public function dump(string $uri, RenderedPage $page): void
    {
        $path = PagePath::written($uri, $page->format, $this->cleanUrls);

        try {
            $this->filesystem->write($path, $page->content);
        } catch (FilesystemException $exception) {
            throw new DumpingException(\sprintf('Cannot dump page for URI "%s".', $uri), previous: $exception);
        }
    }
}
