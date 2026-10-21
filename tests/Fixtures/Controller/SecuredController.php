<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecuredController
{
    #[Prerender]
    #[Route('/secured', name: 'secured')]
    public function secured(): Response
    {
        return new Response('<html><body>Secured</body></html>', headers: [
            'Content-Security-Policy' => "default-src 'self'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Set-Cookie' => 'session=abc',
        ]);
    }

    #[Prerender]
    #[Route('/plain', name: 'plain')]
    public function plain(): Response
    {
        return new Response('<html><body>Plain</body></html>', headers: [
            'Content-Security-Policy' => "default-src 'self'",
        ]);
    }
}
