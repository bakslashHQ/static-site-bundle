<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Event;

/**
 * Dispatched once every page has been processed, even when some failed, e.g. to write a
 * sitemap. Not dispatched when the run is aborted.
 */
final class GenerationFinishedEvent
{
    /**
     * @param list<string> $uris the URIs of the pages written during this run
     */
    public function __construct(
        public readonly array $uris,
    ) {
    }
}
