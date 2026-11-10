<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Prerender]
final class SiteHostController
{
    #[Route('/first', name: 'first', host: 'example.com', schemes: ['https'])]
    public function first(): Response
    {
        return new Response('<html><body>First</body></html>');
    }

    #[Route('/second', name: 'second', host: 'example.com', schemes: ['https'])]
    public function second(): Response
    {
        return new Response('<html><body>Second</body></html>');
    }

    #[Route('/third', name: 'third')]
    public function third(): Response
    {
        return new Response('<html><body>Third</body></html>');
    }
}
