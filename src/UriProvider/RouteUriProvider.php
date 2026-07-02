<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\UriProvider;

use Bakslash\StaticSiteBundle\Exception\InvalidConfigurationException;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Routing\PrerenderRouteLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;
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
    ) {
    }

    public function provide(): iterable
    {
        $baseUrl = $this->router->getContext()->getBaseUrl();

        foreach ($this->generatableRoutes() as $routeName => $params) {
            if (null === $params) {
                yield $this->stripBaseUrl($this->generate($routeName, []), $baseUrl);

                continue;
            }

            foreach ($this->getParamsList($params) as $paramSet) {
                yield $this->stripBaseUrl($this->generate($routeName, $paramSet), $baseUrl);
            }
        }
    }

    /**
     * Validates every prerenderable route up front, so a misconfiguration aborts before any
     * page is written, and maps each route to null (a single page) or the source of its
     * parameter sets.
     *
     * @return array<string, class-string<ParamsProviderInterface>|list<array<string, mixed>>|null>
     */
    private function generatableRoutes(): array
    {
        $routes = [];

        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $config = $route->getDefaults()[PrerenderRouteLoader::ROUTE_DEFAULT] ?? null;
            if (!$config) {
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

    private function stripBaseUrl(string $uri, string $baseUrl): string
    {
        if ('' !== $baseUrl && str_starts_with($uri, $baseUrl)) {
            $uri = substr($uri, \strlen($baseUrl));
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
