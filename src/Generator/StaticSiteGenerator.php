<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Generator;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;

/**
 * @internal
 */
final readonly class StaticSiteGenerator
{
    public function __construct(
        private UriProviderInterface $uriProvider,
        private RendererInterface $renderer,
        private DumperInterface $dumper,
    ) {
    }

    public function purge(): void
    {
        $this->dumper->purge();
    }

    /**
     * @return iterable<GenerationResult>
     */
    public function generate(): iterable
    {
        foreach ($this->uriProvider->provide() as $uri) {
            try {
                $page = $this->renderer->render($uri);
                $this->dumper->dump($uri, $page->content, $page->format);

                yield GenerationResult::success($uri);
            } catch (RenderingException|DumpingException $exception) {
                yield GenerationResult::failure($uri, $exception);
            }
        }
    }
}
