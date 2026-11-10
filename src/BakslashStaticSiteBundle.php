<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Bakslash\StaticSiteBundle\DependencyInjection\Compiler\MinifierPass;
use Bakslash\StaticSiteBundle\Dumper\HeadersFileDumper;
use Bakslash\StaticSiteBundle\Event\GenerationFinishedEvent;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class BakslashStaticSiteBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('base_uri')
                    ->info('The absolute URL the generated site is served from, used for the absolute links baked into the pages (canonical, og:, sitemaps, feeds); it may include a base path (e.g. "https://example.com/docs"). Defaults to the router default URI (framework.router.default_uri) when left empty.')
                    ->defaultValue('')
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => \is_string($value) && '' !== $value && (null === parse_url($value, \PHP_URL_SCHEME) || null === parse_url($value, \PHP_URL_HOST)))
                        ->thenInvalid('The base URI must be empty or an absolute URI including a scheme and host (e.g. "https://example.com"), got %s.')
                    ->end()
                ->end()
                ->booleanNode('minify')
                    ->info('Minify generated HTML and XML pages (requires sensiolabs/minify-bundle).')
                    ->defaultFalse()
                ->end()
                ->booleanNode('clean_urls')
                    ->info('Write HTML pages as "<path>/index.html" instead of "<path>.html", so the site serves clean URLs with no server rewrite rules.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('storage')
                    ->info('Where the generated site is written: a local directory path (a relative one resolves against the working directory), or a Flysystem operator service reference ("@service_id").')
                    ->defaultValue('%kernel.project_dir%/build')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('headers_file')
                    ->info('Write the headers that describe each page to this file of the output, in the "_headers" format of Cloudflare Pages and Netlify (e.g. "_headers"). Disabled when null.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MinifierPass());
    }

    /**
     * @param array{storage: string, base_uri: string, minify: bool, clean_urls: bool, headers_file: ?string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('bakslash_static_site.base_uri', $config['base_uri']);
        $builder->setParameter('bakslash_static_site.minify', $config['minify']);
        $builder->setParameter('bakslash_static_site.clean_urls', $config['clean_urls']);

        $builder->registerForAutoconfiguration(ParamsProviderInterface::class)
            ->addTag(ParamsProviderInterface::TAG);

        $container->import('../config/services.php');

        $this->configureStorage($config['storage'], $builder);

        if (null !== $config['headers_file']) {
            $this->configureHeadersFile($config['headers_file'], $builder);
        }
    }

    private function configureHeadersFile(string $file, ContainerBuilder $builder): void
    {
        // Decorating the dumper records each page as it is written, after every listener. The
        // file is written before the other finish listeners, which may deploy the output.
        $builder->register('.bakslash_static_site.dumper.headers_file', HeadersFileDumper::class)
            ->setDecoratedService('bakslash_static_site.dumper')
            ->setArguments([
                new Reference('.inner'),
                new Reference('bakslash_static_site.filesystem'),
                $file,
                '%bakslash_static_site.clean_urls%',
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.event_listener', ['event' => GenerationFinishedEvent::class, 'method' => 'onGenerationFinished', 'priority' => 256]);
    }

    private function configureStorage(string $storage, ContainerBuilder $builder): void
    {
        if (str_starts_with($storage, '@')) {
            $builder->setAlias('bakslash_static_site.filesystem', substr($storage, 1));

            return;
        }

        $builder->register('bakslash_static_site.filesystem.visibility', PortableVisibilityConverter::class)
            ->setArgument('$defaultForDirectories', Visibility::PUBLIC);

        $builder->register('bakslash_static_site.filesystem', Filesystem::class)
            ->addArgument(new Definition(LocalFilesystemAdapter::class, [$storage, new Reference('bakslash_static_site.filesystem.visibility')]));
    }
}
