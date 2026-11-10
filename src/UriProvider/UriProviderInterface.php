<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

interface UriProviderInterface
{
    /**
     * Yields the URI of each page to generate, restricted by $filter. A parameter
     * set that cannot be turned into a URI may be yielded as a UriGenerationFailure so the run
     * reports it without aborting.
     *
     * The generator consumes the whole iterable before rendering the first page, so no page is
     * rendered and no service is reset while it is being iterated.
     *
     * @return iterable<string|UriGenerationFailure>
     */
    public function provide(GenerationFilter $filter = new GenerationFilter()): iterable;
}
