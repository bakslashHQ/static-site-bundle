<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

final readonly class RenderedPage
{
    public function __construct(
        public string $content,
        public string $format,
    ) {
    }
}
