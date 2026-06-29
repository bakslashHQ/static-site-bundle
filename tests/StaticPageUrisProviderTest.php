<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Prerender;
use Bakslash\StaticSiteBundle\StaticPageUrisProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class StaticPageUrisProviderTest extends TestCase
{
    public function testProvideUris(): void
    {
        $routes = new RouteCollection();
        $routes->add('no_config', new Route('/no-config'));
        $routes->add('static_route', new Route('/static', [
            Prerender::ROUTE_DEFAULTS_KEY => true,
        ]));
        $routes->add('param_route', new Route('/param/{slug}', [
            Prerender::ROUTE_DEFAULTS_KEY => [
                'params' => [[
                    'slug' => 'foo',
                ], [
                    'slug' => 'bar',
                ]],
            ],
        ]));

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $this->createStub(ContainerInterface::class),
        );

        $this->assertSame(['/static', '/param/foo', '/param/bar'], iterator_to_array($provider->provide()));
    }

    public function testProvideUrisWithServiceParamsProvider(): void
    {
        $routes = new RouteCollection();
        $routes->add('service_param', new Route('/service/{param}', [
            Prerender::ROUTE_DEFAULTS_KEY => [
                'params' => 'fooParamProvider',
            ],
        ]));

        $paramProvider = $this->createStub(ParamsProviderInterface::class);
        $paramProvider->method('provideParams')->willReturn([[
            'param' => 'foo',
        ]]);

        $paramsProviders = $this->createStub(ContainerInterface::class);
        $paramsProviders->method('has')->willReturnMap([['fooParamProvider', true]]);
        $paramsProviders->method('get')->willReturnMap([['fooParamProvider', $paramProvider]]);

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $paramsProviders,
        );

        $this->assertSame(['/service/foo'], iterator_to_array($provider->provide()));
    }

    public function testThrowOnRouteNotAcceptingGet(): void
    {
        $routes = new RouteCollection();
        $routes->add('post_route', new Route('/post', [
            Prerender::ROUTE_DEFAULTS_KEY => true,
        ], methods: ['POST']));

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $this->createStub(ContainerInterface::class),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected route "post_route" to accept GET method');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnStatefulRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('stateful', new Route('/stateful', [
            Prerender::ROUTE_DEFAULTS_KEY => true,
            '_stateless' => false,
        ]));

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $this->createStub(ContainerInterface::class),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected route "stateful" to be stateless.');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnMissingParamsConfig(): void
    {
        $routes = new RouteCollection();
        $routes->add('missing_params', new Route('/missing/{param}', [
            Prerender::ROUTE_DEFAULTS_KEY => [
                'foo' => 'bar',
            ],
        ]));

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $this->createStub(ContainerInterface::class),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing "params" configuration for route "missing_params"');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnNonExistentService(): void
    {
        $routes = new RouteCollection();
        $routes->add('bad_service', new Route('/bad/{param}', [
            Prerender::ROUTE_DEFAULTS_KEY => [
                'params' => 'nonExistent',
            ],
        ]));

        $paramsProviders = $this->createStub(ContainerInterface::class);
        $paramsProviders->method('has')->willReturn(false);

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $paramsProviders,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-existent params provider service "nonExistent"');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnInvalidParamsProvider(): void
    {
        $routes = new RouteCollection();
        $routes->add('invalid_service', new Route('/invalid/{param}', [
            Prerender::ROUTE_DEFAULTS_KEY => [
                'params' => 'invalidService',
            ],
        ]));

        $paramsProviders = $this->createStub(ContainerInterface::class);
        $paramsProviders->method('has')->willReturnMap([['invalidService', true]]);
        $paramsProviders->method('get')->willReturnMap([['invalidService', new \stdClass()]]);

        $provider = new StaticPageUrisProvider(
            $this->createRouter($routes),
            $paramsProviders,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not implement');
        iterator_to_array($provider->provide());
    }

    private function createRouter(RouteCollection $routes): Router
    {
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);

        return new Router($loader, 'useless');
    }
}
