<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Dumper;

use Bakslash\StaticSiteBundle\Event\GenerationFinishedEvent;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * Writes the headers that describe each written page into a "_headers" file, the format of
 * Cloudflare Pages and Netlify. The headers every page shares go into one "/*" rule, as
 * Cloudflare Pages reads at most 100 rules.
 *
 * @internal
 */
final class HeadersFileDumper implements DumperInterface
{
    /**
     * Never Set-Cookie, Cache-Control, Vary, ETag or Content-*: they describe one dynamic response.
     */
    private const array WRITTEN = [
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

    private const string BEGIN = '# Written by static-site:generate. Edit outside these lines.';
    private const string END = '# End of static-site:generate.';
    private const int MAX_RULES = 100;

    /** @var array<string, array<string, string>> written headers by served path */
    private array $pages = [];

    public function __construct(
        private readonly DumperInterface $inner,
        private readonly FilesystemOperator $filesystem,
        private readonly string $file,
        private readonly bool $cleanUrls = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function dump(string $uri, RenderedPage $page): void
    {
        $this->inner->dump($uri, $page);

        $this->pages[PagePath::served(PagePath::written($uri, $page->format, $this->cleanUrls))] = self::writtenHeaders($page->headers);
    }

    public function onGenerationFinished(GenerationFinishedEvent $event): void
    {
        [$pages, $this->pages] = [$this->pages, []];

        // A filtered run only sees part of the site, so it cannot tell what every page shares.
        if ($event->partial || [] === $pages) {
            return;
        }

        try {
            [$before, $after] = $this->handWritten($this->filesystem->fileExists($this->file) ? $this->filesystem->read($this->file) : '');

            $block = [self::BEGIN];
            foreach (self::rules($pages) as $path => $headers) {
                $block[] = $path;
                foreach ($headers as $name => $value) {
                    $block[] = \sprintf('  %s: %s', $name, $value);
                }
            }
            $block[] = self::END;

            $lines = [...$before, ...$block, ...$after];
            $this->warnPastTheRuleLimit($lines);

            $this->filesystem->write($this->file, implode("\n", $lines)."\n");
        } catch (FilesystemException $exception) {
            throw new DumpingException(\sprintf('Cannot write the headers file "%s".', $this->file), previous: $exception);
        }
    }

    /**
     * @param list<string> $lines
     */
    private function warnPastTheRuleLimit(array $lines): void
    {
        // Each rule starts with an unindented path, hand-written ones included.
        $count = \count(array_filter($lines, static fn (string $line): bool => str_starts_with($line, '/')));
        if ($count > self::MAX_RULES) {
            $this->logger?->warning('The headers file "{file}" holds {count} rules, while Cloudflare Pages ignores those past the {max}th.', [
                'file' => $this->file,
                'count' => $count,
                'max' => self::MAX_RULES,
            ]);
        }
    }

    /**
     * @param array<string, list<string|null>> $headers
     *
     * @return array<string, string>
     */
    private static function writtenHeaders(array $headers): array
    {
        $written = [];

        foreach (self::WRITTEN as $name) {
            // A header sent several times is one comma-separated header (RFC 9110).
            $value = implode(', ', array_filter($headers[strtolower($name)] ?? [], \is_string(...)));
            if ('' !== $value) {
                $written[$name] = $value;
            }
        }

        return $written;
    }

    /**
     * What every page sending headers shares goes to "/*", and each page keeps a rule for
     * what differs. A page sending none, such as a redirect stub, is left out of "/*".
     *
     * @param array<string, array<string, string>> $pages
     *
     * @return array<string, array<string, string>>
     */
    private static function rules(array $pages): array
    {
        $shared = null;
        foreach ($pages as $headers) {
            if ([] !== $headers) {
                $shared = null === $shared ? $headers : array_intersect_assoc($shared, $headers);
            }
        }
        $shared ??= [];

        $rules = [] !== $shared ? ['/*' => $shared] : [];
        foreach ($pages as $path => $headers) {
            $own = array_diff_assoc($headers, $shared);
            if ([] !== $own) {
                $rules[$path] = $own;
            }
        }

        return $rules;
    }

    /**
     * @return array{list<string>, list<string>} the lines before and after the generated block
     */
    private function handWritten(string $existing): array
    {
        $lines = '' === $existing ? [] : (preg_split('/\R/', rtrim($existing, "\r\n")) ?: []);

        $begin = array_search(self::BEGIN, $lines, true);
        if (false === $begin) {
            return [$lines, []];
        }

        $end = array_search(self::END, \array_slice($lines, $begin, preserve_keys: true), true);
        if (false === $end) {
            throw new DumpingException(\sprintf('Cannot write the headers file "%s": it has the "%s" line but not the "%s" one.', $this->file, self::BEGIN, self::END));
        }

        return [\array_slice($lines, 0, $begin), \array_slice($lines, $end + 1)];
    }
}
