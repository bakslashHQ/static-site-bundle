<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Exception\InvalidConfigurationException;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\UriProvider\RouteUriProvider;
use Bakslash\StaticSiteBundle\UriProvider\UriGenerationFailure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class RouteUriProviderTest extends TestCase
{
    public function testProvideUris(): void
    {
        $routes = new RouteCollection();
        $routes->add('no_config', new Route('/no-config'));
        $routes->add('static_route', new Route('/static', [
            '_static_generation' => true,
        ]));
        $routes->add('param_route', new Route('/param/{slug}', [
            '_static_generation' => [
                'params' => [[
                    'slug' => 'foo',
                ], [
                    'slug' => 'bar',
                ]],
            ],
        ]));

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $this->createStub(ServiceLocator::class),
        );

        $this->assertSame(['/static', '/param/foo', '/param/bar'], iterator_to_array($provider->provide()));
    }

    public function testProvideUrisWithServiceParamsProvider(): void
    {
        $routes = new RouteCollection();
        $routes->add('service_param', new Route('/service/{param}', [
            '_static_generation' => [
                'params' => 'fooParamProvider',
            ],
        ]));

        $paramProvider = $this->createStub(ParamsProviderInterface::class);
        $paramProvider->method('provideParameterSets')->willReturn([[
            'param' => 'foo',
        ]]);

        $paramsProviders = $this->createStub(ServiceLocator::class);
        $paramsProviders->method('has')->willReturnMap([['fooParamProvider', true]]);
        $paramsProviders->method('get')->willReturnMap([['fooParamProvider', $paramProvider]]);

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $paramsProviders,
        );

        $this->assertSame(['/service/foo'], iterator_to_array($provider->provide()));
    }

    public function testThrowOnRouteNotAcceptingGet(): void
    {
        $routes = new RouteCollection();
        $routes->add('post_route', new Route('/post', [
            '_static_generation' => true,
        ], methods: ['POST']));

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $this->createStub(ServiceLocator::class),
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('Expected route "post_route" to accept GET method, it accepts "POST" only.');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnStatefulRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('stateful', new Route('/stateful', [
            '_static_generation' => true,
            '_stateless' => false,
        ]));

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $this->createStub(ServiceLocator::class),
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('Expected route "stateful" to be stateless.');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnMissingParamsConfig(): void
    {
        $routes = new RouteCollection();
        $routes->add('missing_params', new Route('/missing/{param}', [
            '_static_generation' => [
                'foo' => 'bar',
            ],
        ]));

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $this->createStub(ServiceLocator::class),
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('Missing "params" configuration for route "missing_params".');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnParamRouteWithoutParamsProvider(): void
    {
        $routes = new RouteCollection();
        $routes->add('bare_param', new Route('/bare/{id}', [
            '_static_generation' => true,
        ]));

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $this->createStub(ServiceLocator::class),
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('Route "bare_param" has required parameters; configure a "params" provider on its #[Prerender] attribute.');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnNonExistentService(): void
    {
        $routes = new RouteCollection();
        $routes->add('bad_service', new Route('/bad/{param}', [
            '_static_generation' => [
                'params' => 'nonExistent',
            ],
        ]));

        $paramsProviders = $this->createStub(ServiceLocator::class);
        $paramsProviders->method('has')->willReturn(false);

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $paramsProviders,
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs(\sprintf('You have requested a non-existent params provider service "nonExistent". Did you implement "%s"?', ParamsProviderInterface::class));
        iterator_to_array($provider->provide());
    }

    public function testThrowOnInvalidParamsProvider(): void
    {
        $routes = new RouteCollection();
        $routes->add('invalid_service', new Route('/invalid/{param}', [
            '_static_generation' => [
                'params' => 'invalidService',
            ],
        ]));

        $paramsProviders = $this->createStub(ServiceLocator::class);
        $paramsProviders->method('has')->willReturnMap([['invalidService', true]]);
        $paramsProviders->method('get')->willReturnMap([['invalidService', new \stdClass()]]);

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $paramsProviders,
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs(\sprintf('The "invalidService" params provider service does not implement "%s".', ParamsProviderInterface::class));
        iterator_to_array($provider->provide());
    }

    public function testYieldsAFailureForAnInvalidParameterSetWithoutAborting(): void
    {
        $routes = new RouteCollection();
        $routes->add('article', new Route('/article/{slug}', [
            '_static_generation' => [
                'params' => [[
                    'slug' => 'ok',
                ], [
                    'wrong' => 'x',
                ]],
            ],
        ]));

        $provider = new RouteUriProvider(
            $this->createRouter($routes),
            $this->createStub(ServiceLocator::class),
        );

        $results = iterator_to_array($provider->provide());

        $this->assertSame('/article/ok', $results[0]);
        $this->assertInstanceOf(UriGenerationFailure::class, $results[1]);
        $this->assertStringContainsString('route "article"', $results[1]->identifier);
    }

    private function createRouter(RouteCollection $routes): Router
    {
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);

        return new Router($loader, 'useless');
    }
}
