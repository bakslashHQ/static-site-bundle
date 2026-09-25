<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Routing;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
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

            $config = null !== $prerender->params ? [
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

        // Controllers referenced by service id (rather than FQCN) cannot be reflected here.
        if (!class_exists($class)) {
            return null;
        }

        try {
            $methodAttributes = (new \ReflectionMethod($class, $method))->getAttributes(Prerender::class);
            if ([] !== $methodAttributes) {
                return $methodAttributes[0]->newInstance();
            }
        } catch (\ReflectionException) {
            // The method may not exist; fall back to a class-level attribute.
        }

        $classAttributes = (new \ReflectionClass($class))->getAttributes(Prerender::class);

        return [] !== $classAttributes ? $classAttributes[0]->newInstance() : null;
    }
}
