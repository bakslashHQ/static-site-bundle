<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\ArticleController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Exercises the params-provider extension point end to end: a ParamsProviderInterface
 * implementation, only autoconfigured (so the bundle tags it and the locator can resolve
 * it by service id), drives a parameterized route. Also exercises clean-URL output.
 */
final class DynamicRouteKernel extends TestKernel
{
    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/dynamic-site';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('bakslash_static_site', [
            'storage' => $this->getOutputDir(),
            'clean_urls' => true,
        ]);

        $services = $container->services();
        $services->set(SlugParamsProvider::class)->autoconfigure();
        $services->set(ArticleController::class)->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(ArticleController::class, 'attribute');
    }
}
