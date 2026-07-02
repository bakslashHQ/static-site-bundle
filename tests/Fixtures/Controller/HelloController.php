<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Bakslash\StaticSiteBundle\Attribute\Prerender;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelloController
{
    #[Prerender]
    #[Route('/hello', name: 'hello')]
    public function __invoke(): Response
    {
        return new Response("<html>\n  <body>Hello</body>\n</html>");
    }
}
