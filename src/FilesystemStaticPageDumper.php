<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Sensiolabs\MinifyBundle\Minifier\Options\HtmlOptions;
use Symfony\Component\Filesystem\Filesystem;

final readonly class FilesystemStaticPageDumper implements StaticPageDumperInterface
{
    private Filesystem $filesystem;

    public function __construct(
        private MinifierInterface $minify,
        private string $outputDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function dump(string $uri, string $content, ?string $format = null): void
    {
        if ($uri === '/') {
            $fileName = 'index.html';
        } elseif ($format && !str_ends_with($uri, '.' . $format)) {
            $fileName = \sprintf('%s.%s', $uri, $format);
        } else {
            $fileName = $uri;
        }

        $minifyType = match (true) {
            $format === 'html' || str_ends_with($fileName, '.html') => 'html',
            $format === 'xml' || str_ends_with($fileName, '.xml') => 'xml',
            default => null,
        };
        $options = match ($format) {
            'html' => new HtmlOptions(keepDocumentTags: true),
            default => null,
        };

        if ($minifyType) {
            $content = $this->minify->minify($content, $minifyType, $options); // @phpstan-ignore argument.type (the underlying binary supports html/xml), arguments.count
        }

        $this->filesystem->dumpFile(\sprintf('%s/%s', $this->outputDir, $fileName), $content);
    }
}
