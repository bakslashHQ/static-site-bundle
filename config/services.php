<?php

declare(strict_types=1);

use Bakslash\StaticSiteBundle\FilesystemStaticPageDumper;
use Bakslash\StaticSiteBundle\PrerenderRouteLoader;
use Bakslash\StaticSiteBundle\StaticPagesGenerator;
use Bakslash\StaticSiteBundle\StaticPageUrisProvider;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Bakslash\\StaticSiteBundle\\', \dirname(__DIR__) . '/src/')
        ->exclude([
            \dirname(__DIR__) . '/src/BakslashStaticSiteBundle.php',
            \dirname(__DIR__) . '/src/Prerender.php',
        ]);

    $services->get(PrerenderRouteLoader::class)
        ->decorate('routing.loader')
        ->arg('$inner', service('.inner'));

    $services->get(StaticPageUrisProvider::class)
        ->arg('$paramsProviders', tagged_locator('ssg.params_provider', 'key'));

    $services->get(StaticPagesGenerator::class)
        ->arg('$kernel', service('http_kernel'))
        ->arg('$baseUri', '%bakslash_static_site.base_uri%')
        ->arg('$basePath', '%bakslash_static_site.base_path%');

    $services->get(FilesystemStaticPageDumper::class)
        ->arg('$minify', service(MinifierInterface::class))
        ->arg('$outputDir', '%bakslash_static_site.output_dir%');
};
