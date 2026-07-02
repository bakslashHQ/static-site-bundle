<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Bakslash\StaticSiteBundle\BakslashStaticSiteBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
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
        // Keep all generated artifacts (cache, logs, config/reference.php) out of the bundle.
        return sys_get_temp_dir().'/bakslash_static_site_bundle';
    }

    public function getCacheDir(): string
    {
        // Isolate each fixture kernel's cache (notably the compiled route generator/matcher,
        // which is named generically) so one kernel's routes cannot leak into another.
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
