<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Generator;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use Bakslash\StaticSiteBundle\UriProvider\UriGenerationFailure;
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

    /**
     * @return iterable<GenerationResult>
     */
    public function generate(): iterable
    {
        foreach ($this->uriProvider->provide() as $item) {
            if ($item instanceof UriGenerationFailure) {
                yield GenerationResult::failure($item->identifier, $item->error);

                continue;
            }

            try {
                $page = $this->renderer->render($item);
                $this->dumper->dump($item, $page->content, $page->format);

                yield GenerationResult::success($item);
            } catch (RenderingException|DumpingException $exception) {
                yield GenerationResult::failure($item, $exception);
            }
        }
    }
}
