<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\ConfiguredController;
use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\HelloController;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Boots a real route marked with #[Prerender], with minification enabled and backed by
 * a fake minifier, to exercise the whole generation pipeline end to end.
 */
final class GenerationKernel extends TestKernel
{
    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/static-site';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('bakslash_static_site', [
            'minify' => true,
            'storage' => $this->getOutputDir(),
        ]);

        $services = $container->services();
        $services->set(MinifierInterface::class, FakeMinifier::class);
        $services->set(HelloController::class)->tag('controller.service_arguments');
        $services->set(ConfiguredController::class)->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(HelloController::class, 'attribute');

        $routes->add('configured', '/configured')
            ->controller(ConfiguredController::class)
            ->defaults([Prerender::ROUTE_DEFAULTS_KEY => true]);
    }
}
