<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\ParamsProvider;

interface ParamsProviderInterface
{
    /**
     * DI tag automatically applied to every implementation.
     */
    public const string TAG = 'bakslash_static_site.params_provider';

    /**
     * Yields one set of route parameters per page to generate.
     *
     * @return iterable<array<string, mixed>>
     */
    public function provideParameterSets(): iterable;
}
