<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Event;

/**
 * Dispatched when a page cannot be generated: its URI could not be built, or it failed to
 * render or to be written.
 */
final class PageFailedEvent
{
    public function __construct(
        public readonly string $identifier,
        public readonly \Throwable $error,
    ) {
    }
}
