<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

/**
 * Configures "storage" as a "@service" reference, the way an app pointing the bundle at a
 * preconfigured Flysystem operator (e.g. from league/flysystem-bundle) would.
 */
final class StorageServiceKernel extends TestKernel
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->services()
            ->set('custom.filesystem', Filesystem::class)
            ->args([inline_service(LocalFilesystemAdapter::class)->args([sys_get_temp_dir().'/bakslash_custom_storage'])])
            ->public();

        $container->extension('bakslash_static_site', [
            'storage' => '@custom.filesystem',
        ]);
    }
}
