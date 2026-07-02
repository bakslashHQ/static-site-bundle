<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;

final class CustomDumper implements DumperInterface
{
    public function dump(string $uri, string $content, ?string $format = null): void
    {
    }

    public function purge(): void
    {
    }
}
