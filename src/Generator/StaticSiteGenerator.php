<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Generator;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Event\PageDumpedEvent;
use Bakslash\StaticSiteBundle\Event\PageFailedEvent;
use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use Bakslash\StaticSiteBundle\UriProvider\GenerationFilter;
use Bakslash\StaticSiteBundle\UriProvider\UriGenerationFailure;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
final readonly class StaticSiteGenerator
{
    public function __construct(
        private UriProviderInterface $uriProvider,
        private RendererInterface $renderer,
        private DumperInterface $dumper,
        private EventDispatcherInterface $eventDispatcher,
        private ResetInterface $servicesResetter,
    ) {
    }

    /**
     * @return iterable<GenerationResult>
     */
    public function generate(?GenerationFilter $filter = null): iterable
    {
        $seen = [];

        foreach ($this->uriProvider->provide($filter) as $item) {
            if ($item instanceof UriGenerationFailure) {
                $this->eventDispatcher->dispatch(new PageFailedEvent($item->identifier, $item->error));

                yield GenerationResult::failure($item->identifier, $item->error);

                continue;
            }

            // The same URI can be produced twice (duplicate parameter sets, or a static and a
            // generated route colliding); generate it only once, first occurrence wins.
            if (isset($seen[$item])) {
                continue;
            }
            $seen[$item] = true;

            try {
                $event = new PageRenderedEvent($item, $this->renderer->render($item));
                $this->eventDispatcher->dispatch($event);

                $this->dumper->dump($item, $event->getPage());
                $this->eventDispatcher->dispatch(new PageDumpedEvent($item));

                $result = GenerationResult::success($item);
            } catch (RenderingException|DumpingException $exception) {
                $this->eventDispatcher->dispatch(new PageFailedEvent($item, $exception));

                $result = GenerationResult::failure($item, $exception);
            } finally {
                $this->servicesResetter->reset();
            }

            yield $result;
        }
    }
}
