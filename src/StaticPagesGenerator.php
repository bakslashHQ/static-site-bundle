<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final readonly class StaticPagesGenerator
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private string $baseUri,
        private string $basePath,
    ) {
    }

    /**
     * @return array{content: string, format: ?string}
     *
     * @throws \RuntimeException
     */
    public function generate(string $uri): array
    {
        // SCRIPT_NAME tells Symfony which part of the URL is the base path (e.g. "/my-app")
        // so that the router matches routes correctly and path() generates prefixed links.
        $scriptName = $this->basePath . '/index.php';
        $request = Request::create($this->baseUri . $uri, server: [
            'SCRIPT_NAME' => $scriptName,
            'SCRIPT_FILENAME' => $scriptName,
        ]);

        try {
            $response = $this->kernel->handle($request);
        } catch (\Exception $exception) {
            throw new \RuntimeException(\sprintf('Cannot generate page for URI "%s".', $uri), $exception->getCode(), $exception);
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException(\sprintf('Expected URI "%s" to return status code 200, got %d.', $uri, $response->getStatusCode()));
        }

        return [
            'content' => (string) $response->getContent(),
            'format' => $request->getRequestFormat(),
        ];
    }
}
