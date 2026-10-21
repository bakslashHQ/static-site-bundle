<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Renderer;

use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Routing\SiteContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
final readonly class PageRenderer implements RendererInterface
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private SiteContext $siteContext,
    ) {
    }

    /**
     * @throws RenderingException
     */
    public function render(string $uri): RenderedPage
    {
        $request = $this->siteContext->createRequest($uri);

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
            $location = (string) $response->headers->get('Location', '');
            if ('' === $location) {
                throw new RenderingException(\sprintf('URI "%s" returned a %d redirect without a "Location" header.', $uri, $status));
            }

            if (!str_starts_with($location, '/') && !preg_match('#^https?://#i', $location)) {
                throw new RenderingException(\sprintf('URI "%s" redirects to "%s"; a static page can only redirect to an http(s) or site-relative target.', $uri, $location));
            }

            return new RenderedPage($this->redirectStub($location), 'html');
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

        return new RenderedPage($content, $format, $this->headers($response));
    }

    /**
     * Every header of the response, so listeners can read the ones they need (X-Robots-Tag,
     * Link, the security headers, ...). Which of them describe the static page is decided
     * where they are written out.
     *
     * @return array<string, list<string>>
     */
    private function headers(Response $response): array
    {
        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            $values = array_values(array_filter($values, static fn (?string $value): bool => null !== $value));
            if ([] !== $values) {
                $headers[$name] = $values;
            }
        }

        return $headers;
    }

    private function redirectStub(string $location): string
    {
        $target = htmlspecialchars($location, \ENT_QUOTES | \ENT_SUBSTITUTE);

        return \sprintf(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=%s"><link rel="canonical" href="%s"><title>Redirecting</title></head><body>Redirecting to <a href="%s">%s</a>.</body></html>',
            $target,
            $target,
            $target,
            $target,
        );
    }
}
