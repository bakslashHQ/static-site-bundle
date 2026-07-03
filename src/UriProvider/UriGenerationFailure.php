<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

/**
 * Yielded by a UriProviderInterface in place of a URI when a parameter set cannot be turned
 * into one, so the run reports it as a failed page instead of aborting the whole build.
 */
final readonly class UriGenerationFailure
{
    public function __construct(
        public string $identifier,
        public \Throwable $error,
    ) {
    }
}
