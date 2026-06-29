<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Prerender
{
    public const string ROUTE_DEFAULTS_KEY = '_static_generation';

    /**
     * @param class-string<ParamsProviderInterface>|list<array<string, mixed>>|null $params
     */
    public function __construct(
        public string|array|null $params = null,
    ) {
    }
}
