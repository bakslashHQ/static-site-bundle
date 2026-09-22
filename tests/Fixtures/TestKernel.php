<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\BakslashStaticSiteBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new BakslashStaticSiteBundle();
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/bakslash_static_site_bundle/'.substr(md5(__DIR__), 0, 8);
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addResource(new DirectoryResource(\dirname(__DIR__, 2).'/src', '/\.php$/'));
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'/'.(new \ReflectionClass($this))->getShortName();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
