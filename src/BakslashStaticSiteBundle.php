<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class BakslashStaticSiteBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('output_dir')
            ->info('Directory where the generated static pages are dumped.')
            ->defaultValue('%kernel.project_dir%/public')
            ->end()
            ->scalarNode('base_uri')
            ->info('Absolute base URI (scheme + host + base path) used to render the pages.')
            ->defaultValue('')
            ->end()
            ->scalarNode('base_path')
            ->info('Base path under which the site is served (e.g. "/my-app").')
            ->defaultValue('')
            ->end()
            ->end();
    }

    /**
     * @param array{output_dir: string, base_uri: string, base_path: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('bakslash_static_site.output_dir', $config['output_dir']);
        $builder->setParameter('bakslash_static_site.base_uri', $config['base_uri']);
        $builder->setParameter('bakslash_static_site.base_path', $config['base_path']);

        $builder->registerForAutoconfiguration(ParamsProviderInterface::class)
            ->addTag('ssg.params_provider');

        $container->import('../config/services.php');
    }
}
