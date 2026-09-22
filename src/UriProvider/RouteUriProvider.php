<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

use Bakslash\StaticSiteBundle\Exception\InvalidConfigurationException;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Routing\PrerenderRouteLoader;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\RouterInterface;

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
    ) {
    }

    public function provide(?GenerationFilter $filter = null): iterable
    {
        $filter ??= new GenerationFilter();

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

                foreach ($this->getParamsList($params) as $paramSet) {
                    try {
                        $uri = $this->router->generate($routeName, $paramSet, RouterInterface::ABSOLUTE_PATH);
                    } catch (MissingMandatoryParametersException|InvalidParameterException $exception) {
                        // A single bad parameter set (e.g. a null slug) fails only its own page.
                        yield new UriGenerationFailure(\sprintf('route "%s" (%s)', $routeName, json_encode($paramSet) ?: '{}'), $exception);

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
            $config = $route->getDefaults()[PrerenderRouteLoader::ROUTE_DEFAULT] ?? null;
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
            return $this->router->generate($routeName, $parameters, RouterInterface::ABSOLUTE_PATH);
        } catch (MissingMandatoryParametersException $exception) {
            throw new InvalidConfigurationException(\sprintf('Route "%s" has required parameters; configure a "params" provider on its #[Prerender] attribute.', $routeName), previous: $exception);
        }
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
    private function getParamsList(array|string $params): iterable
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

            return $paramsProvider->provideParameterSets();
        }

        return $params;
    }
}
