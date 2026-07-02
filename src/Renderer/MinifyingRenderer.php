<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Sensiolabs\MinifyBundle\Minifier\Options\HtmlOptions;

/**
 * @internal
 */
final readonly class MinifyingRenderer implements RendererInterface
{
    public function __construct(
        private RendererInterface $renderer,
        private MinifierInterface $minify,
    ) {
    }

    public function render(string $uri): RenderedPage
    {
        $page = $this->renderer->render($uri);

        $type = match ($page->format) {
            'html' => 'html',
            'xml' => 'xml',
            default => null,
        };

        if (null === $type) {
            return $page;
        }

        $options = 'html' === $type ? new HtmlOptions(keepDocumentTags: true) : null;

        // minify-bundle's CLI supports XML and per-call options, but its MinifierInterface
        // does not (yet) declare the "xml" type nor the third $options argument.
        $content = $this->minify->minify($page->content, $type, $options); // @phpstan-ignore argument.type, arguments.count

        return new RenderedPage($content, $page->format);
    }
}
