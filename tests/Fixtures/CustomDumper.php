<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;

final class CustomDumper implements DumperInterface
{
    public function dump(string $uri, RenderedPage $page): void
    {
    }
}
