<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Event;

use Bakslash\StaticSiteBundle\Renderer\RenderedPage;

/**
 * Dispatched after a page is rendered and before it is written, so listeners can transform
 * the rendered page (rewrite links, inject markup, ...) via setPage().
 */
final class PageRenderedEvent
{
    public function __construct(
        public readonly string $uri,
        private RenderedPage $page,
    ) {
    }

    public function getPage(): RenderedPage
    {
        return $this->page;
    }

    public function setPage(RenderedPage $page): void
    {
        $this->page = $page;
    }
}
