<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Marked in the routing configuration, the way a controller you do not own has to be.
 */
final class ConfiguredController
{
    public function __invoke(): Response
    {
        return new Response('<html><body>Configured</body></html>');
    }
}
