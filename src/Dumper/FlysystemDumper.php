<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Dumper;

use Bakslash\StaticSiteBundle\Exception\DumpingException;
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

    public function dump(string $uri, string $content, ?string $format = null): void
    {
        $format ??= 'html';

        $path = ltrim(match (true) {
            '/' === $uri => 'index.'.$format,
            str_ends_with($uri, '.'.$format) => $uri,
            'html' === $format && $this->cleanUrls => trim($uri, '/').'/index.html',
            default => $uri.'.'.$format,
        }, '/');

        try {
            $this->filesystem->write($path, $content);
        } catch (FilesystemException $exception) {
            throw new DumpingException(\sprintf('Cannot dump page for URI "%s".', $uri), previous: $exception);
        }
    }
}
