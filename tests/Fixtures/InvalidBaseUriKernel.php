<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Configures a base_uri without a scheme, which the bundle configuration must reject.
 */
final class InvalidBaseUriKernel extends TestKernel
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('bakslash_static_site', ['base_uri' => 'example.com']);
    }
}
