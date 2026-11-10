<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\SiteHostController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The router default URI differs from the site's in host, scheme and base path: the routes
 * generate the site's paths only while the router context is the site's.
 */
final class SiteHostKernel extends TestKernel
{
    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/site-host-site';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('framework', [
            'router' => ['default_uri' => 'http://localhost/app'],
        ]);

        $container->extension('bakslash_static_site', [
            'base_uri' => 'https://example.com/docs',
            'storage' => $this->getOutputDir(),
        ]);

        $container->services()->set(SiteHostController::class)->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(SiteHostController::class, 'attribute');
    }
}
