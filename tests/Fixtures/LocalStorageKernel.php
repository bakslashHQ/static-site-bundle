<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\ArticleController;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Exercises a local storage: modes of nested clean-URL directories, with the bundle's visibility
 * converter or a redefined one (in the "custom_visibility" environment).
 */
final class LocalStorageKernel extends TestKernel
{
    public const CUSTOM_VISIBILITY_ENV = 'custom_visibility';

    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/local-storage-site';
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

        if (self::CUSTOM_VISIBILITY_ENV === $this->environment) {
            $services->set('bakslash_static_site.filesystem.visibility', PortableVisibilityConverter::class)
                ->arg('$directoryPublic', 0o750)
                ->arg('$defaultForDirectories', Visibility::PUBLIC);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(ArticleController::class, 'attribute');
    }
}
