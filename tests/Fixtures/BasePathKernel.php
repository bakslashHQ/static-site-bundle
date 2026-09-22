<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\PagesController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class BasePathKernel extends TestKernel
{
    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/base-path-site';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('framework', [
            'router' => ['default_uri' => 'http://localhost'],
        ]);

        $container->extension('bakslash_static_site', [
            'base_uri' => 'https://example.com/docs',
            'storage' => $this->getOutputDir(),
        ]);

        $container->services()->set(PagesController::class)->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(PagesController::class, 'attribute');
    }
}
