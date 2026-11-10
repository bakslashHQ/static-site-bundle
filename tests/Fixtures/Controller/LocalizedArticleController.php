<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Bakslash\StaticSiteBundle\Tests\Fixtures\LocalizedSlugParamsProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocalizedArticleController
{
    public function __construct(
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    #[Prerender(params: LocalizedSlugParamsProvider::class)]
    #[Route(['en' => '/articles/{slug}', 'fr' => '/fr/articles/{slug}'], name: 'article')]
    public function article(Request $request, string $slug): Response
    {
        return new Response(\sprintf('<html lang="%s"><body>Article %s</body></html>', $request->getLocale(), $slug));
    }

    /**
     * Declared after the localized route, so it is rendered once the French pages are written.
     */
    #[Prerender]
    #[Route('/locale', name: 'locale')]
    public function locale(): Response
    {
        return new Response(\sprintf('<html><body>Locale %s</body></html>', $this->localeSwitcher->getLocale()));
    }
}
