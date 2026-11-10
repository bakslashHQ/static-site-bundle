<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocalizedSlugParamsProvider implements ParamsProviderInterface
{
    private const array SLUGS = [
        'en' => 'hello',
        'fr' => 'bonjour',
    ];

    public function __construct(
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function provideParameterSets(): iterable
    {
        yield ['slug' => self::SLUGS[$this->localeSwitcher->getLocale()]];
    }
}
