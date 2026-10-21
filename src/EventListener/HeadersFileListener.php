<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\EventListener;

use Bakslash\StaticSiteBundle\Event\GenerationFinishedEvent;
use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

/**
 * Writes the headers that describe each page into a "_headers" file, the format Cloudflare
 * Pages and Netlify read, so a static host sends what the application would have sent.
 * Registered only when the "headers_file" option is set.
 *
 * Cloudflare Pages reads at most 100 rules, so the headers every page shares go into one
 * "/*" rule, and a page gets a rule of its own only for what differs.
 *
 * The rules live between two comment lines: what is written outside them is kept verbatim,
 * and a run filtered with --route or --path updates its pages among those already listed
 * there instead of replacing them, in line with the command never deleting anything.
 *
 * @internal
 */
final class HeadersFileListener
{
    /**
     * The headers that describe the page itself, and so hold for its static copy. Never
     * Set-Cookie, Cache-Control, Vary, ETag or Content-*: they describe one dynamic response.
     */
    public const WRITTEN = [
        'Content-Security-Policy',
        'Content-Security-Policy-Report-Only',
        'Strict-Transport-Security',
        'X-Content-Type-Options',
        'X-Frame-Options',
        'Referrer-Policy',
        'Permissions-Policy',
        'Cross-Origin-Opener-Policy',
        'Cross-Origin-Embedder-Policy',
        'Cross-Origin-Resource-Policy',
        'X-Robots-Tag',
        'Link',
    ];

    private const BEGIN = '# Written by static-site:generate. Edit outside these lines.';
    private const END = '# End of static-site:generate.';
    private const ONLY_SHARED = ' (the /* rule only)';

    /** @var array<string, array{format: string, headers: array<string, string>}> by URI */
    private array $rendered = [];

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly SiteContext $siteContext,
        private readonly string $file,
        private readonly bool $cleanUrls = false,
    ) {
    }

    public function onPageRendered(PageRenderedEvent $event): void
    {
        $page = $event->getPage();
        $this->rendered[$event->uri] = ['format' => $page->format, 'headers' => self::written($page->headers)];
    }

    public function onGenerationFinished(GenerationFinishedEvent $event): void
    {
        $pages = [];
        foreach ($event->uris as $uri) {
            if (isset($this->rendered[$uri])) {
                $pages[$this->servedPath($uri, $this->rendered[$uri]['format'])] = $this->rendered[$uri]['headers'];
            }
        }
        $this->rendered = [];

        if ([] === $pages) {
            return;
        }

        try {
            $existing = $this->filesystem->fileExists($this->file) ? $this->filesystem->read($this->file) : '';
            $this->filesystem->write($this->file, self::merge($existing, $pages));
        } catch (FilesystemException $exception) {
            throw new DumpingException(\sprintf('Cannot write the headers file "%s".', $this->file), previous: $exception);
        }
    }

    /**
     * Updates the pages given among those already listed between the markers, lays them out
     * again ("/*" for what all share), and keeps every line outside the markers.
     *
     * @param array<string, array<string, string>> $pages headers by served path
     */
    public static function merge(string $existing, array $pages): string
    {
        $lines = '' === $existing ? [] : (preg_split('/\R/', rtrim($existing, "\r\n")) ?: []);
        $begin = array_search(self::BEGIN, $lines, true);
        $end = false === $begin ? false : array_search(self::END, \array_slice($lines, $begin, preserve_keys: true), true);

        if (false === $begin || false === $end) {
            [$before, $known, $after] = [$lines, [], []];
        } else {
            [$before, $known, $after] = [
                \array_slice($lines, 0, $begin),
                self::parse(\array_slice($lines, $begin + 1, $end - $begin - 1)),
                \array_slice($lines, $end + 1),
            ];
        }

        foreach ($pages as $path => $headers) {
            $known[$path] = $headers;
        }

        return implode("\n", [...$before, ...self::layout($known), ...$after])."\n";
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array<string, string>
     */
    private static function written(array $headers): array
    {
        $written = [];

        foreach (self::WRITTEN as $name) {
            $values = $headers[strtolower($name)] ?? [];
            // A header sent several times is one comma-separated header (RFC 9110), and a
            // line break would end the rule and let the value forge another one.
            $value = implode(', ', $values);
            if ('' !== $value && 1 !== preg_match('/[\r\n]/', $value)) {
                $written[$name] = $value;
            }
        }

        return $written;
    }

    /**
     * @param array<string, array<string, string>> $pages
     *
     * @return list<string>
     */
    private static function layout(array $pages): array
    {
        // What every page sends with the same value goes to "/*", once.
        $shared = null;
        foreach ($pages as $headers) {
            $shared = null === $shared ? $headers : array_intersect_assoc($shared, $headers);
        }
        $shared ??= [];

        $lines = [self::BEGIN];
        if ([] !== $shared) {
            $lines = [...$lines, '/*', ...self::headerLines($shared)];
        }
        foreach ($pages as $path => $headers) {
            $own = array_diff_assoc($headers, $shared);
            $lines = [] === $own ? [...$lines, '# '.$path.self::ONLY_SHARED] : [...$lines, $path, ...self::headerLines($own)];
        }
        $lines[] = self::END;

        return $lines;
    }

    /**
     * The full headers of each page listed between the markers: the "/*" rule plus its own.
     *
     * @param list<string> $lines
     *
     * @return array<string, array<string, string>>
     */
    private static function parse(array $lines): array
    {
        $rules = [];
        $path = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, '# ') && str_ends_with($line, self::ONLY_SHARED)) {
                $rules[substr($line, 2, -\strlen(self::ONLY_SHARED))] = [];
                $path = null;
            } elseif ('' !== trim($line) && !str_starts_with($line, '#') && !ctype_space($line[0])) {
                $path = trim($line);
                $rules[$path] = [];
            } elseif (null !== $path && 1 === preg_match('/^\s+([^:\s]+):\s*(.*)$/', $line, $match)) {
                $rules[$path][$match[1]] = $match[2];
            }
        }

        $shared = $rules['/*'] ?? [];
        unset($rules['/*']);

        return array_map(static fn (array $own): array => $own + $shared, $rules);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private static function headerLines(array $headers): array
    {
        return array_map(static fn (string $name, string $value): string => \sprintf('  %s: %s', $name, $value), array_keys($headers), $headers);
    }

    /**
     * The URL the host serves the written file at: what its rules match against.
     */
    private function servedPath(string $uri, string $format): string
    {
        $path = match (true) {
            str_ends_with($uri, '/'), str_ends_with($uri, '.'.$format) => $uri,
            // "<path>/index.html" is served at "<path>/".
            'html' === $format && $this->cleanUrls => rtrim($uri, '/').'/',
            // "<path>.html" is served at "<path>": both hosts drop the extension.
            'html' === $format => $uri,
            default => $uri.'.'.$format,
        };

        return $this->siteContext->basePath().$path;
    }
}
