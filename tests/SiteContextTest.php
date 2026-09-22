<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Routing\SiteContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;

final class SiteContextTest extends TestCase
{
    #[DataProvider('basePathProvider')]
    public function testBasePath(string $baseUri, string $expectedBasePath): void
    {
        $this->assertSame($expectedBasePath, $this->siteContext($baseUri)->basePath());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function basePathProvider(): iterable
    {
        yield 'sub-path' => ['https://example.com/docs', '/docs'];
        yield 'sub-path with trailing slash' => ['https://example.com/docs/', '/docs'];
        yield 'root' => ['https://example.com', ''];
        yield 'root with trailing slash' => ['https://example.com/', ''];
    }

    public function testCreateRequestDerivesBasePathFromBaseUri(): void
    {
        $request = $this->siteContext('https://example.com/my-app/')->createRequest('/foo');

        $this->assertSame('https://example.com/my-app/foo', $request->getUri());
        $this->assertSame('/my-app', $request->getBasePath());
        $this->assertSame('/foo', $request->getPathInfo());
    }

    #[DataProvider('scriptNameProvider')]
    public function testCreateRequestSetsScriptServerVars(string $baseUri, string $expectedScriptName): void
    {
        $request = $this->siteContext($baseUri)->createRequest('/foo');

        $this->assertSame($expectedScriptName, $request->server->get('SCRIPT_NAME'));
        $this->assertSame($expectedScriptName, $request->server->get('SCRIPT_FILENAME'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scriptNameProvider(): iterable
    {
        yield 'sub-path' => ['https://example.com/my-app', '/my-app/index.php'];
        yield 'sub-path with trailing slash' => ['https://example.com/my-app/', '/my-app/index.php'];
        yield 'no path' => ['http://localhost', '/index.php'];
        yield 'empty base uri' => ['', '/index.php'];
    }

    #[DataProvider('requestContextProvider')]
    public function testFallsBackToTheRequestContextWhenNoBaseUriIsConfigured(RequestContext $requestContext, string $expectedUri): void
    {
        $this->assertSame($expectedUri, (new SiteContext($requestContext, ''))->createRequest('/foo')->getUri());
    }

    /**
     * @return iterable<string, array{RequestContext, string}>
     */
    public static function requestContextProvider(): iterable
    {
        yield 'scheme, host and base path' => [new RequestContext('/my-app', 'GET', 'example.com', 'https'), 'https://example.com/my-app/foo'];
        yield 'non-standard port' => [new RequestContext('', 'GET', 'example.com', 'http', 8080), 'http://example.com:8080/foo'];
    }

    public function testEnterPointsTheRequestContextAtTheSite(): void
    {
        $requestContext = new RequestContext();

        (new SiteContext($requestContext, 'https://example.com/docs'))->enter();

        $this->assertSame('https', $requestContext->getScheme());
        $this->assertSame('example.com', $requestContext->getHost());
        $this->assertSame('/docs', $requestContext->getBaseUrl());
    }

    public function testLeavePutsTheRequestContextBack(): void
    {
        $requestContext = new RequestContext('/app', 'GET', 'localhost', 'http');
        $siteContext = new SiteContext($requestContext, 'https://example.com/docs');

        $siteContext->enter();
        $siteContext->leave();

        $this->assertSame('http', $requestContext->getScheme());
        $this->assertSame('localhost', $requestContext->getHost());
        $this->assertSame('/app', $requestContext->getBaseUrl());
    }

    public function testLeaveWithoutEnterChangesNothing(): void
    {
        $requestContext = new RequestContext('/app', 'GET', 'localhost', 'http');

        (new SiteContext($requestContext, 'https://example.com/docs'))->leave();

        $this->assertSame('/app', $requestContext->getBaseUrl());
        $this->assertSame('localhost', $requestContext->getHost());
    }

    public function testLeaveAfterRepeatedEnterPutsTheOriginalContextBack(): void
    {
        $requestContext = new RequestContext('/app', 'GET', 'localhost', 'http');
        $siteContext = new SiteContext($requestContext, 'https://example.com/docs');

        $siteContext->enter();
        $siteContext->enter();
        $siteContext->leave();

        $this->assertSame('/app', $requestContext->getBaseUrl());
        $this->assertSame('localhost', $requestContext->getHost());
    }

    private function siteContext(string $baseUri): SiteContext
    {
        return new SiteContext(new RequestContext(), $baseUri);
    }
}
