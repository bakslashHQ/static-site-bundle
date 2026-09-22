<?php

declare(strict_types=1);

use Bakslash\StaticSiteBundle\Command\GenerateStaticSiteCommand;
use Bakslash\StaticSiteBundle\Command\ListStaticSiteCommand;
use Bakslash\StaticSiteBundle\Dumper\FlysystemDumper;
use Bakslash\StaticSiteBundle\Generator\StaticSiteGenerator;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Renderer\PageRenderer;
use Bakslash\StaticSiteBundle\Routing\PrerenderRouteLoader;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use Bakslash\StaticSiteBundle\UriProvider\RouteUriProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('.bakslash_static_site.routing.prerender_route_loader', PrerenderRouteLoader::class)
        ->decorate('routing.loader')
        ->args([
            service('.inner'),
        ]);

    $services->set('.bakslash_static_site.site_context', SiteContext::class)
        ->args([
            service('router.request_context'),
            param('bakslash_static_site.base_uri'),
        ]);

    $services->set('bakslash_static_site.uri_provider', RouteUriProvider::class)
        ->args([
            service('router'),
            tagged_locator(ParamsProviderInterface::TAG),
            service('.bakslash_static_site.site_context'),
        ]);

    $services->set('bakslash_static_site.renderer', PageRenderer::class)
        ->args([
            service('http_kernel'),
            service('.bakslash_static_site.site_context'),
        ]);

    $services->set('bakslash_static_site.dumper', FlysystemDumper::class)
        ->args([
            service('bakslash_static_site.filesystem'),
            param('bakslash_static_site.clean_urls'),
        ]);

    $services->set('.bakslash_static_site.generator', StaticSiteGenerator::class)
        ->args([
            service('bakslash_static_site.uri_provider'),
            service('bakslash_static_site.renderer'),
            service('bakslash_static_site.dumper'),
            service('event_dispatcher'),
            service('services_resetter'),
        ]);

    $services->set('.bakslash_static_site.command.generate', GenerateStaticSiteCommand::class)
        ->args([
            service('.bakslash_static_site.generator'),
        ])
        ->tag('console.command');

    $services->set('.bakslash_static_site.command.list', ListStaticSiteCommand::class)
        ->args([
            service('bakslash_static_site.uri_provider'),
        ])
        ->tag('console.command');
};
