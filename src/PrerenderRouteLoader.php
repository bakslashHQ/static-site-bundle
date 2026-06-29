<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;

final readonly class PrerenderRouteLoader implements LoaderInterface
{
    public function __construct(
        private LoaderInterface $inner,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $collection = $this->inner->load($resource, $type);

        if (!$collection instanceof RouteCollection) {
            return $collection;
        }

        foreach ($collection as $route) {
            $controller = $route->getDefault('_controller');
            if (!\is_string($controller)) {
                continue;
            }

            if (isset($route->getDefaults()[Prerender::ROUTE_DEFAULTS_KEY])) {
                continue;
            }

            $prerender = $this->resolvePrerenderAttribute($controller);
            if (!$prerender instanceof Prerender) {
                continue;
            }

            $config = $prerender->params !== null ? [
                'params' => $prerender->params,
            ] : true;
            $route->addDefaults([
                Prerender::ROUTE_DEFAULTS_KEY => $config,
            ]);
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }

    private function resolvePrerenderAttribute(string $controller): ?Prerender
    {
        if (str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller, 2);
        } else {
            $class = $controller;
            $method = '__invoke';
        }

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return null;
        }

        $attributes = $reflection->getAttributes(Prerender::class);

        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }
}
