<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;

final class CustomRenderer implements RendererInterface
{
    public function render(string $uri): RenderedPage
    {
        return new RenderedPage('custom', 'html');
    }
}
