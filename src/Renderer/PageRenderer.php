<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @internal
 */
final readonly class PageRenderer implements RendererInterface
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private ServicesResetterInterface $servicesResetter,
        private RequestContext $requestContext,
        private string $baseUri,
    ) {
    }

    /**
     * @throws RenderingException
     */
    public function render(string $uri): RenderedPage
    {
        $baseUri = rtrim('' !== $this->baseUri ? $this->baseUri : $this->baseUriFromContext(), '/');

        $basePath = (string) parse_url($baseUri, \PHP_URL_PATH);
        $scriptName = $basePath.'/index.php';

        $request = Request::create($baseUri.$uri, server: [
            'SCRIPT_NAME' => $scriptName,
            'SCRIPT_FILENAME' => $scriptName,
        ]);

        try {
            try {
                $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            } catch (\Throwable $exception) {
                throw new RenderingException(\sprintf('Cannot render page for URI "%s": %s', $uri, $exception->getMessage()), previous: $exception);
            }

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new RenderingException(\sprintf('Expected URI "%s" to return HTTP 200, got %d.', $uri, $response->getStatusCode()));
            }

            $content = $response->getContent();
            if (false === $content) {
                throw new RenderingException(\sprintf('URI "%s" returned a response whose content cannot be captured (streamed and binary responses are not supported).', $uri));
            }

            return new RenderedPage($content, $request->getRequestFormat() ?? 'html');
        } finally {
            $this->servicesResetter->reset();
        }
    }

    /**
     * Falls back to the router's request context (i.e. "framework.router.default_uri").
     */
    private function baseUriFromContext(): string
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
