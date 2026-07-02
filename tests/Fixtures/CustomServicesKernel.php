<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Overrides the bundle's renderer and dumper with custom implementations, the way a
 * consuming application would.
 */
final class CustomServicesKernel extends TestKernel
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $services = $container->services();
        $services->set('bakslash_static_site.renderer', CustomRenderer::class);
        $services->set('bakslash_static_site.dumper', CustomDumper::class);
    }
}
