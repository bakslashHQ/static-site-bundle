<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\Tests\Fixtures\Controller\SecuredController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Boots routes whose responses carry security headers, with the given "headers_file".
 */
final class HeadersFileKernel extends TestKernel
{
    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $headersFile = '_headers',
    ) {
        parent::__construct($environment, $debug);
    }

    public function getOutputDir(): string
    {
        return $this->getProjectDir().'/static-site-headers';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/'.md5((string) $this->headersFile);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('bakslash_static_site', [
            'storage' => $this->getOutputDir(),
            'headers_file' => $this->headersFile,
        ]);

        $container->services()->set(SecuredController::class)->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(SecuredController::class, 'attribute');
    }
}
