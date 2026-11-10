<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

final readonly class RenderedPage
{
    /**
     * @param array<string, list<string|null>> $headers the headers of the response the page was
     *                                                  rendered from, keyed by lowercase name
     */
    public function __construct(
        public string $content,
        public string $format,
        public array $headers = [],
    ) {
    }

    public function withContent(string $content): self
    {
        return new self($content, $this->format, $this->headers);
    }
}
