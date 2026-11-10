<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Bakslash\StaticSiteBundle\Exception\InvalidConfigurationException;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * @internal
 */
final readonly class RouteUriProvider implements UriProviderInterface
{
    /**
     * @param ServiceLocator<object> $paramsProviders
     */
    public function __construct(
        private RouterInterface $router,
        private ServiceLocator $paramsProviders,
        private SiteContext $siteContext,
        private ?LocaleSwitcher $localeSwitcher = null,
    ) {
    }

    public function provide(GenerationFilter $filter = new GenerationFilter()): iterable
    {
        try {
            $this->siteContext->enter();

            foreach ($this->generatableRoutes($filter) as $routeName => $params) {
                if (null === $params) {
                    $uri = $this->stripBasePath($this->generate($routeName, []));
                    if ($filter->matchesPath($uri)) {
                        yield $uri;
                    }

                    continue;
                }

                foreach ($this->getParamsList($routeName, $params) as $paramSet) {
                    try {
                        $uri = $this->router->generate($routeName, $paramSet, RouterInterface::ABSOLUTE_PATH);
                    } catch (MissingMandatoryParametersException|InvalidParameterException $exception) {
                        // A single bad parameter set (e.g. a null slug) fails only its own page.
                        yield new UriGenerationFailure($this->pageIdentifier($routeName, $paramSet), $exception);

                        continue;
                    }

                    if ('' === $uri) {
                        yield new UriGenerationFailure(
                            $this->pageIdentifier($routeName, $paramSet),
                            new InvalidConfigurationException(\sprintf('Expected route "%s" to generate a URI, got an empty one; its parameters do not satisfy the route requirements.', $routeName)),
                        );

                        continue;
                    }

                    if (null !== $error = $this->offSiteError($routeName, $uri)) {
                        yield new UriGenerationFailure($this->pageIdentifier($routeName, $paramSet), $error);

                        continue;
                    }

                    // Parameters the route has no placeholder for land in the query string.
                    if (false !== strpbrk($uri, '?#')) {
                        yield new UriGenerationFailure(
                            $this->pageIdentifier($routeName, $paramSet),
                            new InvalidConfigurationException(\sprintf('Expected route "%s" to generate a path without a query string or fragment, got "%s".', $routeName, $uri)),
                        );

                        continue;
                    }

                    $uri = $this->stripBasePath($uri);
                    if ($filter->matchesPath($uri)) {
                        yield $uri;
                    }
                }
            }
        } finally {
            $this->siteContext->leave();
        }
    }

    /**
     * Validates every prerenderable route up front, so a misconfiguration aborts before any
     * page is written, and maps each route to null (a single page) or the source of its
     * parameter sets.
     *
     * @return array<string, class-string<ParamsProviderInterface>|list<array<string, mixed>>|null>
     */
    private function generatableRoutes(GenerationFilter $filter): array
    {
        $routes = [];

        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $config = $route->getDefaults()[Prerender::ROUTE_DEFAULTS_KEY] ?? null;
            if (!$config) {
                continue;
            }

            if (!$filter->matchesRoute($routeName)) {
                continue;
            }

            $methods = $route->getMethods();
            if ([] !== $methods && !\in_array('GET', $methods, true)) {
                throw new InvalidConfigurationException(\sprintf('Expected route "%s" to accept GET method, it accepts "%s" only.', $routeName, implode(', ', $methods)));
            }

            if (false === ($route->getDefaults()['_stateless'] ?? null)) {
                throw new InvalidConfigurationException(\sprintf('Expected route "%s" to be stateless.', $routeName));
            }

            if ([] === $route->compile()->getPathVariables() || true === $config) {
                $routes[$routeName] = null;

                continue;
            }

            if (!\is_array($config) || !isset($config['params']) || (!\is_string($config['params']) && !\is_array($config['params']))) {
                throw new InvalidConfigurationException(\sprintf('Missing "params" configuration for route "%s".', $routeName));
            }

            /** @var class-string<ParamsProviderInterface>|list<array<string, mixed>> $params */
            $params = $config['params'];
            $routes[$routeName] = $params;
        }

        return $routes;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generate(string $routeName, array $parameters): string
    {
        try {
            $uri = $this->router->generate($routeName, $parameters, RouterInterface::ABSOLUTE_PATH);
        } catch (MissingMandatoryParametersException $exception) {
            throw new InvalidConfigurationException(\sprintf('Route "%s" has required parameters; configure a "params" provider on its #[Prerender] attribute.', $routeName), previous: $exception);
        }

        if ('' === $uri) {
            throw new InvalidConfigurationException(\sprintf('Expected route "%s" to generate a URI, got an empty one; its defaults do not satisfy the route requirements.', $routeName));
        }

        if (null !== $error = $this->offSiteError($routeName, $uri)) {
            throw $error;
        }

        return $uri;
    }

    /**
     * The generator falls back to a network path ("//host/path") or an absolute URL when the
     * route requires a host or scheme other than the site's, which no written file can serve.
     */
    private function offSiteError(string $routeName, string $uri): ?InvalidConfigurationException
    {
        if (str_starts_with($uri, '/') && !str_starts_with($uri, '//')) {
            return null;
        }

        return new InvalidConfigurationException(\sprintf('Expected route "%s" to generate a path on the site host, got "%s"; a static site cannot serve another host or scheme.', $routeName, $uri));
    }

    /**
     * @param array<string, mixed> $paramSet
     */
    private function pageIdentifier(string $routeName, array $paramSet): string
    {
        return \sprintf('Route "%s" (%s)', $routeName, json_encode($paramSet) ?: '{}');
    }

    private function stripBasePath(string $uri): string
    {
        $basePath = $this->siteContext->basePath();

        if ('' !== $basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, \strlen($basePath));
        }

        return '' !== $uri ? $uri : '/';
    }

    /**
     * @param string|list<array<string, mixed>> $params
     *
     * @return iterable<array<string, mixed>>
     */
    private function getParamsList(string $routeName, array|string $params): iterable
    {
        if (\is_string($params)) {
            $serviceId = $params;
            if (!$this->paramsProviders->has($serviceId)) {
                throw new InvalidConfigurationException(\sprintf('You have requested a non-existent params provider service "%s". Did you implement "%s"?', $serviceId, ParamsProviderInterface::class));
            }

            $paramsProvider = $this->paramsProviders->get($serviceId);
            if (!$paramsProvider instanceof ParamsProviderInterface) {
                throw new InvalidConfigurationException(\sprintf('The "%s" params provider service does not implement "%s".', $serviceId, ParamsProviderInterface::class));
            }

            // Drained up front: a suspended provider would be caught by the reset between pages.
            $drain = static fn (): array => iterator_to_array($paramsProvider->provideParameterSets(), false);

            $locale = $this->routeLocale($routeName);
            if (null !== $this->localeSwitcher && null !== $locale) {
                return $this->localeSwitcher->runWithLocale($locale, $drain);
            }

            return $drain();
        }

        return $params;
    }

    /**
     * The locale a localized route (one path per locale) is bound to, so its params provider
     * can run in it and yield locale-dependent values, such as translated slugs. A route with
     * a "{_locale}" placeholder serves every locale, so it is not bound to its default one.
     */
    private function routeLocale(string $routeName): ?string
    {
        $route = $this->router->getRouteCollection()->get($routeName);
        $locale = $route?->getDefault('_locale');

        if (!\is_string($locale) || \in_array('_locale', $route->compile()->getPathVariables(), true)) {
            return null;
        }

        return $locale;
    }
}
