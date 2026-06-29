<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

interface StaticPageUrisProviderInterface
{
    /**
     * @return iterable<string>
     */
    public function provide(): iterable;
}
