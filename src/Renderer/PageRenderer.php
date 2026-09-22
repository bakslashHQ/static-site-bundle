<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
final readonly class PageRenderer implements RendererInterface
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private ResetInterface $servicesResetter,
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
                // The kernel is handled but deliberately never terminate()d, so kernel.terminate
                // work (deferred emails, Messenger dispatch, ...) does not fire for every page.
                // "catch: false" lets a real controller error surface instead of becoming a 500.
                $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            } catch (\Throwable $exception) {
                throw new RenderingException(\sprintf('Cannot render page for URI "%s": %s', $uri, $exception->getMessage()), previous: $exception);
            }

            $status = $response->getStatusCode();

            if ($status >= 300 && $status < 400) {
                // A redirect becomes a static meta-refresh stub pointing at its target.
                return new RenderedPage($this->redirectStub((string) $response->headers->get('Location', '')), 'html');
            }

            if (Response::HTTP_OK !== $status) {
                throw new RenderingException(\sprintf('Expected URI "%s" to return HTTP 200 or a redirect, got %d.', $uri, $status));
            }

            $content = $response->getContent();
            if (false === $content) {
                throw new RenderingException(\sprintf('URI "%s" returned a response whose content cannot be captured (streamed and binary responses are not supported).', $uri));
            }

            // The extension follows the route's explicit format if it declares one, else what
            // the content actually is (the response Content-Type), else HTML.
            $contentType = $response->headers->get('Content-Type');
            $format = $request->getRequestFormat(null)
                ?? ($contentType ? $request->getFormat($contentType) : null)
                ?? 'html';

            return new RenderedPage($content, $format);
        } finally {
            $this->servicesResetter->reset();
        }
    }

    private function redirectStub(string $location): string
    {
        $target = htmlspecialchars($location, \ENT_QUOTES);

        return \sprintf(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=%s"><link rel="canonical" href="%s"><title>Redirecting</title></head><body>Redirecting to <a href="%s">%s</a>.</body></html>',
            $target,
            $target,
            $target,
            $target,
        );
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
