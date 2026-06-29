<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

interface ParamsProviderInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function provideParams(): iterable;
}
