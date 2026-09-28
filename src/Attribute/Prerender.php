<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Attribute;

/**
 * Marks a controller method, or every action of a controller class, to be prerendered into a
 * static page. A method-level attribute overrides a class-level one.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class Prerender
{
    /**
     * The routing configuration equivalent of this attribute:
     * ->defaults([Prerender::ROUTE_DEFAULTS_KEY => true]).
     */
    public const string ROUTE_DEFAULTS_KEY = '_static_generation';

    /**
     * @param string|list<array<string, mixed>>|null $params either the service id of a ParamsProviderInterface implementation or an inline list of parameter sets
     */
    public function __construct(
        public string|array|null $params = null,
    ) {
    }
}
