<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Exception\InvalidConfigurationException;
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use Bakslash\StaticSiteBundle\UriProvider\GenerationFilter;
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
    use ExpectsExceptionMessage;

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

        $provider = $this->createProvider($routes);

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

        $provider = $this->createProvider($routes, $paramsProviders);

        $this->assertSame(['/service/foo'], iterator_to_array($provider->provide()));
    }

    public function testThrowOnRouteNotAcceptingGet(): void
    {
        $routes = new RouteCollection();
        $routes->add('post_route', new Route('/post', [
            '_static_generation' => true,
        ], methods: ['POST']));

        $provider = $this->createProvider($routes);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageToBe('Expected route "post_route" to accept GET method, it accepts "POST" only.');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnStatefulRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('stateful', new Route('/stateful', [
            '_static_generation' => true,
            '_stateless' => false,
        ]));

        $provider = $this->createProvider($routes);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageToBe('Expected route "stateful" to be stateless.');
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

        $provider = $this->createProvider($routes);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageToBe('Missing "params" configuration for route "missing_params".');
        iterator_to_array($provider->provide());
    }

    public function testThrowOnParamRouteWithoutParamsProvider(): void
    {
        $routes = new RouteCollection();
        $routes->add('bare_param', new Route('/bare/{id}', [
            '_static_generation' => true,
        ]));

        $provider = $this->createProvider($routes);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageToBe('Route "bare_param" has required parameters; configure a "params" provider on its #[Prerender] attribute.');
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

        $provider = $this->createProvider($routes, $paramsProviders);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageToBe(\sprintf('You have requested a non-existent params provider service "nonExistent". Did you implement "%s"?', ParamsProviderInterface::class));
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

        $provider = $this->createProvider($routes, $paramsProviders);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageToBe(\sprintf('The "invalidService" params provider service does not implement "%s".', ParamsProviderInterface::class));
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

        $provider = $this->createProvider($routes);

        $results = iterator_to_array($provider->provide());

        $this->assertSame('/article/ok', $results[0]);
        $this->assertInstanceOf(UriGenerationFailure::class, $results[1]);
        $this->assertStringContainsString('Route "article"', $results[1]->identifier);
    }

    public function testYieldsAFailureWhenParametersEndUpInTheQueryString(): void
    {
        $routes = new RouteCollection();
        $routes->add('article', new Route('/article/{slug}', [
            '_static_generation' => [
                'params' => [[
                    'slug' => 'ok',
                ], [
                    'slug' => 'extra',
                    'page' => 2,
                ]],
            ],
        ]));

        $results = iterator_to_array($this->createProvider($routes)->provide());

        $this->assertSame('/article/ok', $results[0]);
        $this->assertInstanceOf(UriGenerationFailure::class, $results[1]);
        $this->assertSame('Route "article" ({"slug":"extra","page":2})', $results[1]->identifier);
        $this->assertSame('Expected route "article" to generate a path without a query string or fragment, got "/article/extra?page=2".', $results[1]->error->getMessage());
    }

    public function testFiltersByRouteName(): void
    {
        $routes = new RouteCollection();
        $routes->add('blog', new Route('/blog', ['_static_generation' => true]));
        $routes->add('shop', new Route('/shop', ['_static_generation' => true]));

        $provider = $this->createProvider($routes);

        $this->assertSame(['/blog'], iterator_to_array($provider->provide(new GenerationFilter(routes: ['blog']))));
    }

    public function testFiltersByPath(): void
    {
        $routes = new RouteCollection();
        $routes->add('blog', new Route('/blog', ['_static_generation' => true]));
        $routes->add('shop', new Route('/shop', ['_static_generation' => true]));

        $provider = $this->createProvider($routes);

        $this->assertSame(['/shop'], iterator_to_array($provider->provide(new GenerationFilter(paths: ['/shop*']))));
    }

    /**
     * @param ServiceLocator<object>|null $paramsProviders
     */
    private function createProvider(RouteCollection $routes, ?ServiceLocator $paramsProviders = null): RouteUriProvider
    {
        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);

        $router = new Router($loader, 'useless');

        return new RouteUriProvider(
            $router,
            $paramsProviders ?? $this->createStub(ServiceLocator::class),
            new SiteContext($router->getContext(), ''),
        );
    }
}
