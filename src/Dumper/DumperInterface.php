<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Dumper;

use Bakslash\StaticSiteBundle\Exception\DumpingException;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;

interface DumperInterface
{
    /**
     * @throws DumpingException
     */
    public function dump(string $uri, RenderedPage $page): void;
}
