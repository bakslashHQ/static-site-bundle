<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Attribute;

/**
 * Marks a controller method, or an invokable controller class, to be prerendered into a static page.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class Prerender
{
    /**
     * @param string|list<array<string, mixed>>|null $params either the service id of a ParamsProviderInterface implementation or an inline list of parameter sets
     */
    public function __construct(
        public string|array|null $params = null,
    ) {
    }
}
