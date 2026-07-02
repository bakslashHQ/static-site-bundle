<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;

final class SlugParamsProvider implements ParamsProviderInterface
{
    public function provideParameterSets(): iterable
    {
        yield ['slug' => 'foo'];
        yield ['slug' => 'bar'];
    }
}
