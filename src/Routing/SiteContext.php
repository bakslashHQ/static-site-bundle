<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

/**
 * @internal
 */
final class SiteContext
{
    private readonly string $baseUri;
    private ?RequestContext $snapshot = null;

    public function __construct(
        private readonly RequestContext $requestContext,
        string $configuredBaseUri,
    ) {
        $this->baseUri = rtrim('' !== $configuredBaseUri ? $configuredBaseUri : $this->baseUriFromRequestContext(), '/');
    }

    public function basePath(): string
    {
        return (string) parse_url($this->baseUri, \PHP_URL_PATH);
    }

    public function createRequest(string $uri): Request
    {
        $scriptName = $this->basePath().'/index.php';

        return Request::create($this->baseUri.$uri, server: [
            'SCRIPT_NAME' => $scriptName,
            'SCRIPT_FILENAME' => $scriptName,
        ]);
    }

    public function enter(): void
    {
        $this->snapshot ??= clone $this->requestContext;
        $this->requestContext->fromRequest($this->createRequest('/'));
    }

    public function leave(): void
    {
        if (null === $this->snapshot) {
            return;
        }

        $snapshot = $this->snapshot;
        $this->snapshot = null;

        $this->requestContext
            ->setScheme($snapshot->getScheme())
            ->setHost($snapshot->getHost())
            ->setHttpPort($snapshot->getHttpPort())
            ->setHttpsPort($snapshot->getHttpsPort())
            ->setBaseUrl($snapshot->getBaseUrl())
            ->setPathInfo($snapshot->getPathInfo())
            ->setMethod($snapshot->getMethod())
            ->setQueryString($snapshot->getQueryString());
    }

    private function baseUriFromRequestContext(): string
    {
        $scheme = $this->requestContext->getScheme();

        $port = '';
        if ('http' === $scheme && 80 !== $this->requestContext->getHttpPort()) {
            $port = ':'.$this->requestContext->getHttpPort();
        } elseif ('https' === $scheme && 443 !== $this->requestContext->getHttpsPort()) {
            $port = ':'.$this->requestContext->getHttpsPort();
        }

        return \sprintf('%s://%s%s%s', $scheme, $this->requestContext->getHost(), $port, $this->requestContext->getBaseUrl());
    }
}
