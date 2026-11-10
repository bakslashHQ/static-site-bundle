<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Dumper;

/**
 * @internal
 */
final class PagePath
{
    /**
     * The path of the file a page is written to, relative to the output.
     */
    public static function written(string $uri, string $format, bool $cleanUrls): string
    {
        return ltrim(match (true) {
            str_ends_with($uri, '/') => $uri.'index.'.$format,
            str_ends_with($uri, '.'.$format) => $uri,
            'html' === $format && $cleanUrls => trim($uri, '/').'/index.html',
            default => $uri.'.'.$format,
        }, '/');
    }

    /**
     * The URL a static host serves a written file at: "index.html" as its directory, and
     * other HTML files without their extension.
     */
    public static function served(string $written): string
    {
        $path = '/'.$written;

        return match (true) {
            str_ends_with($path, '/index.html') => substr($path, 0, -\strlen('index.html')),
            str_ends_with($path, '.html') => substr($path, 0, -\strlen('.html')),
            default => $path,
        };
    }
}
