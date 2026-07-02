<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Enables minification without registering SensiolabsMinifyBundle, so the container
 * build fails with a meaningful exception.
 */
final class MinifyKernel extends TestKernel
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('bakslash_static_site', ['minify' => true]);
    }
}
