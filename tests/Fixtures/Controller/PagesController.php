<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController
{
    #[Prerender]
    #[Route('/one', name: 'one')]
    public function one(): Response
    {
        return new Response('<html><body>One</body></html>');
    }

    #[Prerender]
    #[Route('/two', name: 'two')]
    public function two(): Response
    {
        return new Response('<html><body>Two</body></html>');
    }

    #[Prerender]
    #[Route('/three', name: 'three')]
    public function three(): Response
    {
        return new Response('<html><body>Three</body></html>');
    }
}
