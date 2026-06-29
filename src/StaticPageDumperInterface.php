<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

interface StaticPageDumperInterface
{
    public function dump(string $uri, string $content, ?string $format = null): void;
}
