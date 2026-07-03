<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Event;

/**
 * Dispatched after a page has been written, e.g. to collect the generated URIs for a sitemap.
 */
final class PageDumpedEvent
{
    public function __construct(
        public readonly string $uri,
    ) {
    }
}
