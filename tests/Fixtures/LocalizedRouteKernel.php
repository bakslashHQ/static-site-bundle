<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\LocalizedArticleController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Translation\LocaleSwitcher;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Exercises localized routes: each locale variant of a route runs its params provider in its
 * own locale, so the provider can yield per-locale values such as translated slugs.
 */
final class LocalizedRouteKernel extends TestKernel
{
    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/localized-site';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('framework', [
            'default_locale' => 'en',
            'enabled_locales' => ['en', 'fr'],
        ]);

        $container->extension('bakslash_static_site', [
            'storage' => $this->getOutputDir(),
        ]);

        $services = $container->services();

        // FrameworkBundle drops its definition when symfony/translation is a dev dependency, as
        // here, even with the translator enabled. This mirrors it, reset and locale-aware tags included.
        $services->set('translation.locale_switcher', LocaleSwitcher::class)
            ->args([
                param('kernel.default_locale'),
                tagged_iterator('kernel.locale_aware', exclude: 'translation.locale_switcher'),
                service('router.request_context'),
            ])
            ->tag('kernel.reset', ['method' => 'reset'])
            ->tag('kernel.locale_aware')
            ->alias(LocaleSwitcher::class, 'translation.locale_switcher');

        $services->set(LocalizedSlugParamsProvider::class)->autowire()->autoconfigure();
        $services->set(LocalizedArticleController::class)->autowire()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(LocalizedArticleController::class, 'attribute');
    }
}
