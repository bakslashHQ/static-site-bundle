<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Bakslash\StaticSiteBundle\Tests\Fixtures\SlugParamsProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController
{
    #[Prerender(params: SlugParamsProvider::class)]
    #[Route('/articles/{slug}', name: 'article')]
    public function __invoke(string $slug): Response
    {
        return new Response(\sprintf('<html><body>Article %s</body></html>', $slug));
    }
}
