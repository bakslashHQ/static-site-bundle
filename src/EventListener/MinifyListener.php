<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\EventListener;

use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Sensiolabs\MinifyBundle\Minifier\Options\HtmlOptions;

/**
 * Minifies rendered HTML and XML pages. Registered on PageRenderedEvent only when the
 * "minify" option is enabled.
 *
 * @internal
 */
final readonly class MinifyListener
{
    public function __construct(
        private MinifierInterface $minify,
    ) {
    }

    public function __invoke(PageRenderedEvent $event): void
    {
        $page = $event->getPage();

        $type = match ($page->format) {
            'html' => 'html',
            'xml' => 'xml',
            default => null,
        };

        if (null === $type) {
            return;
        }

        $options = 'html' === $type ? new HtmlOptions(keepDocumentTags: true) : null;

        // minify-bundle's CLI supports XML and per-call options, but its MinifierInterface
        // does not (yet) declare the "xml" type nor the third $options argument.
        $content = $this->minify->minify($page->content, $type, $options); // @phpstan-ignore argument.type, arguments.count

        $event->setPage(new RenderedPage($content, $page->format));
    }
}
