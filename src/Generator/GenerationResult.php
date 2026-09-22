<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Generator;

/**
 * @internal
 */
final readonly class GenerationResult
{
    private function __construct(
        public string $uri,
        public ?\Throwable $error = null,
    ) {
    }

    public static function success(string $uri): self
    {
        return new self($uri);
    }

    public static function failure(string $uri, \Throwable $error): self
    {
        return new self($uri, $error);
    }
}
