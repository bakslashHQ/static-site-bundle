<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\RouterInterface;

final readonly class StaticPageUrisProvider implements StaticPageUrisProviderInterface
{
    public function __construct(
        private RouterInterface $router,
        private ContainerInterface $paramsProviders,
    ) {
    }

    public function provide(): iterable
    {
        $baseUrl = $this->router->getContext()->getBaseUrl();

        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $config = $route->getDefaults()[Prerender::ROUTE_DEFAULTS_KEY] ?? null;
            if (!$config) {
                continue;
            }

            if ($route->getMethods() !== [] && !\in_array('GET', $route->getMethods(), true)) {
                throw new LogicException(\sprintf('Expected route "%s" to accept GET method, it accepts "%s" only.', $routeName, implode(', ', $route->getMethods())));
            }

            if (($route->getDefaults()['_stateless'] ?? null) === false) {
                throw new LogicException(\sprintf('Expected route "%s" to be stateless.', $routeName));
            }

            $compiledRoute = $route->compile();

            if ($compiledRoute->getPathVariables() === [] || $config === true) {
                yield $this->stripBaseUrl($this->router->generate($routeName, [], RouterInterface::ABSOLUTE_PATH), $baseUrl);

                continue;
            }

            if (!\is_array($config) || !isset($config['params']) || (!\is_string($config['params']) && !\is_array($config['params']))) {
                throw new LogicException(\sprintf('Missing "params" configuration for route "%s".', $routeName));
            }

            /** @var class-string<ParamsProviderInterface>|list<array<string, mixed>> $params */
            $params = $config['params'];
            foreach ($this->getParamsList($params) as $paramSet) {
                yield $this->stripBaseUrl($this->router->generate($routeName, $paramSet, RouterInterface::ABSOLUTE_PATH), $baseUrl);
            }
        }
    }

    private function stripBaseUrl(string $uri, string $baseUrl): string
    {
        if ($baseUrl !== '' && str_starts_with($uri, $baseUrl)) {
            $uri = substr($uri, \strlen($baseUrl));
        }

        return $uri !== '' && $uri !== '0' ? $uri : '/';
    }

    /**
     * @param class-string<ParamsProviderInterface>|list<array<string, mixed>> $params
     *
     * @return iterable<array<string, mixed>>
     */
    private function getParamsList(array|string $params): iterable
    {
        if (\is_string($params)) {
            $serviceId = $params;
            if (!$this->paramsProviders->has($serviceId)) {
                throw new InvalidArgumentException(\sprintf('You have requested a non-existent params provider service "%s". Did you implement "%s"?', $serviceId, ParamsProviderInterface::class));
            }

            $paramsProvider = $this->paramsProviders->get($serviceId);
            if (!$paramsProvider instanceof ParamsProviderInterface) {
                throw new InvalidArgumentException(\sprintf('The "%s" params provider service does not implement "%s".', $serviceId, ParamsProviderInterface::class));
            }

            return $paramsProvider->provideParams();
        }

        return $params;
    }
}
