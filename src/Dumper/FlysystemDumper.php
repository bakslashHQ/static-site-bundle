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
    ) {
    }

    public function dump(string $uri, string $content, ?string $format = null): void
    {
        $path = ltrim(match (true) {
            '/' === $uri => 'index.html',
            $format && !str_ends_with($uri, '.'.$format) => \sprintf('%s.%s', $uri, $format),
            default => $uri,
        }, '/');

        try {
            $this->filesystem->write($path, $content);
        } catch (FilesystemException $exception) {
            throw new DumpingException(\sprintf('Cannot dump page for URI "%s".', $uri), previous: $exception);
        }
    }
}
