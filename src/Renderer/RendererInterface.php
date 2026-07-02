<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

use Bakslash\StaticSiteBundle\Exception\RenderingException;

interface RendererInterface
{
    /**
     * @throws RenderingException
     */
    public function render(string $uri): RenderedPage;
}
