<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

interface UriProviderInterface
{
    /**
     * @return iterable<string>
     */
    public function provide(): iterable;
}
