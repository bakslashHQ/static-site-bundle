<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Routing\PrerenderRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class PrerenderRouteLoaderTest extends TestCase
{
    public function testSetsStaticGenerationFromPrerenderAttribute(): void
    {
        $routes = new RouteCollection();
        $routes->add('prerender_route', new Route('/prerender', [
            '_controller' => PrerenderController::class,
        ]));

        $collection = $this->load($routes);

        $route = $collection->get('prerender_route');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertTrue($route->getDefault('_static_generation'));
    }

    public function testSetsStaticGenerationWithParams(): void
    {
        $routes = new RouteCollection();
        $routes->add('prerender_param', new Route('/prerender/{id}', [
            '_controller' => PrerenderWithParamsController::class,
        ]));

        $collection = $this->load($routes);

        $route = $collection->get('prerender_param');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame(
            [
                'params' => StubParamsProvider::class,
            ],
            $route->getDefault('_static_generation'),
        );
    }

    public function testDoesNotOverrideExistingStaticGeneration(): void
    {
        $routes = new RouteCollection();
        $routes->add('already_set', new Route('/already', [
            '_controller' => PrerenderController::class,
            '_static_generation' => [
                'params' => [[
                    'id' => 'custom',
                ]],
            ],
        ]));

        $collection = $this->load($routes);

        $route = $collection->get('already_set');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame(
            [
                'params' => [[
                    'id' => 'custom',
                ]],
            ],
            $route->getDefault('_static_generation'),
        );
    }

    public function testSkipsRoutesWithoutPrerender(): void
    {
        $routes = new RouteCollection();
        $routes->add('no_prerender', new Route('/no-prerender', [
            '_controller' => NoAttributeController::class,
        ]));

        $collection = $this->load($routes);

        $route = $collection->get('no_prerender');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertNull($route->getDefault('_static_generation'));
    }

    public function testSkipsRoutesWithoutController(): void
    {
        $routes = new RouteCollection();
        $routes->add('no_controller', new Route('/no-controller'));

        $collection = $this->load($routes);

        $route = $collection->get('no_controller');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertNull($route->getDefault('_static_generation'));
    }

    public function testSetsStaticGenerationFromClassLevelAttribute(): void
    {
        $routes = new RouteCollection();
        $routes->add('class_level', new Route('/class-level', [
            '_controller' => ClassLevelPrerenderController::class,
        ]));

        $collection = $this->load($routes);

        $route = $collection->get('class_level');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertTrue($route->getDefault('_static_generation'));
    }

    public function testSkipsControllerThatIsNotAClass(): void
    {
        $routes = new RouteCollection();
        $routes->add('service_controller', new Route('/service', [
            '_controller' => 'app.some_controller::index',
        ]));

        $collection = $this->load($routes);

        $route = $collection->get('service_controller');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertNull($route->getDefault('_static_generation'));
    }

    private function load(RouteCollection $routes): RouteCollection
    {
        $inner = $this->createStub(LoaderInterface::class);
        $inner->method('load')->willReturn($routes);

        $loader = new PrerenderRouteLoader($inner);

        $result = $loader->load('resource');
        $this->assertInstanceOf(RouteCollection::class, $result);

        return $result;
    }
}

final class PrerenderController
{
    #[Prerender]
    public function __invoke(): void
    {
    }
}

final class PrerenderWithParamsController
{
    #[Prerender(params: StubParamsProvider::class)]
    public function __invoke(): void
    {
    }
}

#[Prerender]
final class ClassLevelPrerenderController
{
    public function __invoke(): void
    {
    }
}

final class NoAttributeController
{
    public function __invoke(): void
    {
    }
}

final class StubParamsProvider implements ParamsProviderInterface
{
    public function provideParameterSets(): iterable
    {
        return [];
    }
}
