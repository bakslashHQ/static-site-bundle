<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

/**
 * Restricts a run to a subset of routes and/or URIs. An empty filter matches everything.
 */
final readonly class GenerationFilter
{
    /**
     * @param list<string> $routes fnmatch() patterns matched against each route name
     * @param list<string> $paths  fnmatch() patterns matched against each generated URI
     */
    public function __construct(
        private array $routes = [],
        private array $paths = [],
    ) {
    }

    public function matchesRoute(string $routeName): bool
    {
        return $this->matches($this->routes, $routeName);
    }

    public function matchesPath(string $uri): bool
    {
        return $this->matches($this->paths, $uri);
    }

    /**
     * @param list<string> $patterns
     */
    private function matches(array $patterns, string $value): bool
    {
        if ([] === $patterns) {
            return true;
        }

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
